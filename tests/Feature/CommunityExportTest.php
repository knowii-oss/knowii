<?php

use App\Actions\Communities\ExportCommunity;
use App\Contracts\Communities\ExportsCommunities;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiSpaceType;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventAnswer;
use App\Models\MemberTag;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use App\Models\User;
use App\Support\MemberTagAssignments;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/*
 * The export of a community (#1353): the return of the data that the DPA promises.
 */

/**
 * A community with an owner, a member, a post, a comment, a chat message, a reaction, an upload with its file and a
 * direct message, and another community that must stay out of the export.
 *
 * @return array{community: Community, owner: User, member: User, former: User, upload: Upload, post: Post}
 */
function communityExportFixture(): array
{
    Storage::fake('local');
    Storage::fake('public');

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $member = User::factory()->create(['email' => 'member@example.com']);
    $former = User::factory()->create(['email' => 'former@example.com']);
    $community = Community::factory()->create(['owner_id' => $owner->id, 'name' => 'Export Club']);
    $community->users()->attach($member, ['role' => KnowiiCommunityMemberRole::Admin]);
    $community->communityInvitations()->create(['email' => 'invited@example.com', 'role' => 'member']);

    $group = SpaceGroup::factory()->create(['community_id' => $community->id]);
    $space = Space::factory()->inGroup($group)->create();
    $chat = Space::factory()->inGroup($group)->create(['type' => 'chat']);

    $post = Post::factory()->inSpace($space)->create(['author_id' => $member->id, 'body' => 'First post of the club']);
    // A person who left the community, and a deleted account
    Post::factory()->inSpace($space)->create(['author_id' => $former->id, 'body' => 'Written before leaving']);
    Post::factory()->inSpace($space)->create(['author_id' => null, 'body' => 'Written by a deleted account']);
    Comment::factory()->onPost($post)->create(['author_id' => $owner->id, 'body' => 'Welcome to the club']);
    ChatMessage::factory()->inSpace($chat)->create(['author_id' => $member->id, 'body' => 'Hello in the chat']);
    (new Reaction(['community_id' => $community->id, 'user_id' => $owner->id, 'emoji' => '👍']))->forceFill(['post_id' => $post->id])->save();

    Storage::disk('public')->put('uploads/diagram.png', 'PNG-BYTES');
    $upload = Upload::create([
        'community_id' => $community->id, 'user_id' => $member->id, 'disk' => 'public', 'path' => 'uploads/diagram.png',
        'mime_type' => 'image/png', 'width' => 10, 'height' => 10, 'size_bytes' => 9, 'alt' => 'A diagram',
    ]);

    $conversation = Conversation::create(['community_id' => $community->id, 'created_by' => $member->id]);
    $conversation->participants()->attach([$owner->id, $member->id]);
    $direct = ChatMessage::factory()->state(['space_id' => null, 'conversation_id' => $conversation->id, 'community_id' => $community->id, 'author_id' => $member->id, 'body' => 'Private words between two people'])->create();
    (new Reaction(['community_id' => $community->id, 'user_id' => $owner->id, 'emoji' => '❤️']))->forceFill(['chat_message_id' => $direct->id])->save();

    // Another community: nothing of it may leak
    $other = Community::factory()->create();
    $otherSpace = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $other->id]))->create();
    Post::factory()->inSpace($otherSpace)->create(['body' => 'Secret of the other community']);

    return ['community' => $community, 'owner' => $owner, 'member' => $member, 'former' => $former, 'upload' => $upload, 'post' => $post];
}

/**
 * Runs the command and reads the zip file that it prints.
 *
 * @return array<string, string> the content of each file of the zip, by name
 */
function runCommunityExport(Community $community, bool $withDirectMessages = false): array
{
    $status = Artisan::call('communities:export', ['slug' => $community->slug] + ($withDirectMessages ? ['--with-direct-messages' => true] : []));
    expect($status)->toBe(0);

    $path = trim(Artisan::output());
    expect($path)->toEndWith('.zip')->and(file_exists($path))->toBeTrue();

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $files = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = (string) $zip->getNameIndex($index);
        $files[$name] = (string) $zip->getFromIndex($index);
    }
    $zip->close();

    return $files;
}

test('the export holds the members with their email addresses, the content and the files', function () {
    $f = communityExportFixture();

    $files = runCommunityExport($f['community']);
    $community = json_decode($files['community.json'], true);
    $posts = json_decode($files['posts.json'], true);

    expect(array_keys($files))->toContain('README.txt', 'community.json', 'posts.json', 'comments.json', 'pages.json', 'chat-messages.json', 'reactions.json', 'resources.json', 'quizzes.json', 'quiz-results.json', 'lesson-progress.json', 'course-materials.json', 'uploads.json')
        ->and($community['format_version'])->toBe(1)
        ->and($community['community']['cuid'])->toBe($f['community']->cuid)
        ->and($community['community']['owner'])->toBe($f['owner']->cuid)
        ->and($community['community']['dpa_accepted_at'])->toBeNull()
        ->and(array_column($community['members'], 'email'))->toBe(['owner@example.com', 'member@example.com'])
        ->and($community['members'][0]['role'])->toBe('owner')
        ->and($community['members'][1]['role'])->toBe('admin')
        ->and($community['members'][1]['user'])->toBe($f['member']->cuid)
        ->and($community['invitations'][0]['email'])->toBe('invited@example.com')
        ->and($community['space_groups'])->toHaveCount(1)
        ->and($community['spaces'])->toHaveCount(2)
        ->and($files['comments.json'])->toContain('Welcome to the club')
        ->and($files['chat-messages.json'])->toContain('Hello in the chat')
        ->and($files['reactions.json'])->toContain('👍');

    $bodies = array_column($posts['posts'], 'body');
    expect($bodies)->toContain('First post of the club', 'Written before leaving', 'Written by a deleted account');

    // A person who left comes with a username and a name, a deleted account is null
    $byBody = array_column($posts['posts'], null, 'body');
    expect($byBody['First post of the club']['author'])->toBe($f['member']->cuid)
        ->and($byBody['First post of the club'])->not->toHaveKey('author_details')
        ->and($byBody['Written before leaving']['author_details'])->toBe(['username' => $f['former']->username, 'name' => $f['former']->name])
        ->and($byBody['Written by a deleted account']['author'])->toBeNull();

    $uploads = json_decode($files['uploads.json'], true);
    $entry = 'files/uploads/'.$f['upload']->cuid.'-diagram.png';
    expect($uploads['uploads'][0]['file'])->toBe($entry)
        ->and($uploads['uploads'][0]['alt'])->toBe('A diagram')
        ->and($files[$entry])->toBe('PNG-BYTES');
});

test('the export is one zip file under exports/ of the local disk, named after the community', function () {
    $f = communityExportFixture();

    Artisan::call('communities:export', ['slug' => $f['community']->slug]);
    $path = trim(Artisan::output());

    expect(Storage::disk('local')->files(ExportCommunity::DIRECTORY))->toHaveCount(1)
        ->and(basename($path))->toMatch('/^community-export-club-\d{8}-\d{6}\.zip$/')
        ->and($path)->toBe(Storage::disk('local')->path(ExportCommunity::DIRECTORY.'/'.basename($path)));
});

test('direct messages are left out, unless the operator asks for them', function () {
    $f = communityExportFixture();

    $without = runCommunityExport($f['community']);

    expect($without)->not->toHaveKey('conversations.json')
        ->and(implode('', $without))->not->toContain('Private words between two people')
        ->and($without['reactions.json'])->not->toContain('❤️')
        ->and($without['README.txt'])->toContain('direct messages');

    $with = runCommunityExport($f['community'], true);
    $conversations = json_decode($with['conversations.json'], true);

    expect($conversations['conversations'])->toHaveCount(1)
        ->and(array_column($conversations['conversations'][0]['participants'], 'user'))->toContain($f['owner']->cuid, $f['member']->cuid)
        ->and($conversations['conversations'][0]['messages'][0]['body'])->toBe('Private words between two people')
        ->and($with['reactions.json'])->toContain('❤️')
        ->and($with['chat-messages.json'])->not->toContain('Private words between two people');
});

test('the export has no internal id, password, derived HTML or data of another community', function () {
    $f = communityExportFixture();

    $files = runCommunityExport($f['community'], true);
    $all = implode("\n", $files);

    expect($all)->not->toContain('body_html')
        ->and($all)->not->toContain($f['owner']->password)
        ->and($all)->not->toContain('"password"')
        ->and($all)->not->toContain('two_factor')
        ->and($all)->not->toContain('remember_token')
        ->and($all)->not->toContain('Secret of the other community');

    foreach ($files as $name => $content) {
        if (str_ends_with($name, '.json')) {
            expect(json_decode($content, true))->toBeArray()->and($content)->not->toMatch('/"(id|owner_id|community_id|user_id|post_id|space_id|author_id)":/');
        }
    }
});

test('the export is logged with the community, not with its content', function () {
    $f = communityExportFixture();
    Log::spy();

    runCommunityExport($f['community']);

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => $message === 'Community exported' && $context['community'] === $f['community']->cuid)->once();
});

test('the command says when no community has the slug', function () {
    Storage::fake('local');

    $this->artisan('communities:export', ['slug' => 'nobody-home'])->expectsOutputToContain('No community has this slug.')->assertFailed();
});

test('the export action is behind its contract', function () {
    expect(app(ExportsCommunities::class))->toBeInstanceOf(ExportCommunity::class);
});

test('the export holds the events with their Markdown, never their HTML or their internal id (#1513)', function () {
    $f = communityExportFixture();
    $events = Space::factory()->inGroup(SpaceGroup::query()->where('community_id', $f['community']->id)->firstOrFail())->create(['type' => KnowiiSpaceType::Events]);
    $event = Event::factory()->inSpace($events)->hostedBy($f['member'])->create([
        'title' => 'Weekly review office hour',
        'body' => 'Bring **your** questions',
        'body_html' => '<p>Bring <strong>your</strong> questions</p>',
        'starts_at' => '2027-03-23 16:00:00',
        'ends_at' => '2027-03-23 17:00:00',
        'place' => 'Room 2',
        'cover_upload_id' => $f['upload']->id,
    ]);

    $files = runCommunityExport($f['community']);
    $rows = json_decode($files['events.json'], true)['events'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['cuid'])->toBe($event->cuid)
        ->and($rows[0]['space'])->toBe($events->cuid)
        ->and($rows[0]['host'])->toBe($f['member']->cuid)
        ->and($rows[0]['body'])->toBe('Bring **your** questions')
        ->and($rows[0]['starts_at'])->toBe('2027-03-23T16:00:00+00:00')
        ->and($rows[0]['time_zone'])->toBe('Europe/Brussels')
        ->and($rows[0]['place'])->toBe('Room 2')
        ->and($rows[0]['state'])->toBe('published')
        ->and($rows[0]['cover_upload'])->toBe($f['upload']->cuid)
        ->and($rows[0])->not->toHaveKey('body_html')
        ->and($rows[0])->not->toHaveKey('id')
        ->and($files['README.txt'])->toContain('events.json');
});

test('the export holds the limit of places and the replay choice of an event, and no answer of a member (#1515)', function () {
    $f = communityExportFixture();
    $events = Space::factory()->inGroup(SpaceGroup::query()->where('community_id', $f['community']->id)->firstOrFail())->create(['type' => KnowiiSpaceType::Events]);
    $event = Event::factory()->inSpace($events)->hostedBy($f['member'])->create(['capacity' => 40, 'replay_expected' => true]);
    $answer = EventAnswer::factory()->to($event)->of($f['member'])->replay()->create();

    $files = runCommunityExport($f['community']);
    $rows = json_decode($files['events.json'], true)['events'];

    expect($rows[0]['capacity'])->toBe(40)
        ->and($rows[0]['replay_expected'])->toBeTrue()
        ->and(array_keys($files))->not->toContain('event-answers.json');
    foreach ($files as $name => $contents) {
        // Not `toContain`: a failure would print the whole file
        expect(str_contains($contents, $answer->cuid))->toBeFalse($name)
            ->and(str_contains($contents, 'event_answers'))->toBeFalse($name)
            ->and(str_contains($contents, '"answer"'))->toBeFalse($name);
    }
});

test('the export holds the member tags of both visibilities with the cuids of their members, and none of another community (#1389)', function () {
    $f = communityExportFixture();
    $founder = MemberTag::factory()->forMembers()->create(['community_id' => $f['community']->id, 'name' => 'Founder', 'color' => 'purple']);
    $onboarded = MemberTag::factory()->forManagers()->create(['community_id' => $f['community']->id, 'name' => 'Onboarded']);
    MemberTagAssignments::add($founder, $f['owner']);
    MemberTagAssignments::add($founder, $f['member']);
    MemberTagAssignments::add($onboarded, $f['member']);
    MemberTag::factory()->forMembers()->create(['name' => 'Qxelsewhere']);

    $files = runCommunityExport($f['community']);
    $community = json_decode($files['community.json'], true);
    $tags = collect($community['member_tags'])->keyBy('name');

    expect(array_search('member_tags', array_keys($community), true))->toBe(array_search('access_groups', array_keys($community), true) + 1)
        ->and($tags->keys()->sort()->values()->all())->toBe(['Founder', 'Onboarded'])
        ->and(array_keys($tags['Founder']))->toBe(['cuid', 'name', 'color', 'visibility', 'members', 'created_at'])
        ->and($tags['Founder']['cuid'])->toBe($founder->cuid)
        ->and($tags['Founder']['color'])->toBe('purple')
        ->and($tags['Founder']['visibility'])->toBe('members')
        ->and(collect($tags['Founder']['members'])->sort()->values()->all())->toBe(collect([$f['owner']->cuid, $f['member']->cuid])->sort()->values()->all())
        ->and($tags['Onboarded']['visibility'])->toBe('managers')
        ->and($tags['Onboarded']['members'])->toBe([$f['member']->cuid])
        ->and($files['community.json'])->not->toContain('Qxelsewhere')
        ->and($files['README.txt'])->toContain('member tags');
});
