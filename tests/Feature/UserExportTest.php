<?php

use App\Actions\Users\ExportUser;
use App\Actions\Users\UpdateAvatar;
use App\Contracts\Users\ExportsUsers;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiInvitationImportOutcome;
use App\Models\AccessGroup;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\InvitationImport;
use App\Models\InvitationImportRow;
use App\Models\InviteLink;
use App\Models\InviteLinkUse;
use App\Models\MemberTag;
use App\Models\Notification;
use App\Models\NotificationFollow;
use App\Models\NotificationSpaceLevel;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\InvitationGrants;
use App\Support\MemberTagAssignments;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/*
 * The export of the data of a member (#1343): the copy that the privacy policy promises (section 8).
 */

/**
 * A member with a profile, an avatar, a membership, a post, a comment on the post of someone else, a chat message, a
 * reaction, a curated resource, an upload with its file, a block, a token, a session and a direct message. Another
 * member has a post and a direct message in the same community: they must stay out of the export.
 *
 * @return array{member: User, other: User, community: Community, upload: Upload, post: Post, token: string, tokenHash: string}
 */
function userExportFixture(): array
{
    Storage::fake('local');
    Storage::fake('public');
    Storage::fake(UpdateAvatar::disk());

    $member = User::factory()->create(['email' => 'member@example.com', 'name' => 'Marie Member', 'email_verified_at' => now()]);
    $profile = UserProfile::factory()->create(['user_id' => $member->id, 'name' => 'Marie Member', 'headline' => 'Grows tomatoes on a balcony', 'bio' => 'I grow tomatoes', 'location' => 'Brussels', 'phone' => '+32 000 00 00']);
    $other = User::factory()->create(['email' => 'other@example.com']);
    $owner = User::factory()->create(['email' => 'owner@example.com']);

    $community = Community::factory()->create(['owner_id' => $owner->id, 'name' => 'Garden Club']);
    $community->users()->attach($member, ['role' => KnowiiCommunityMemberRole::Moderator]);
    $community->users()->attach($other, ['role' => KnowiiCommunityMemberRole::Member]);
    $community->communityInvitations()->create(['email' => 'member@example.com', 'role' => 'member']);

    $group = SpaceGroup::factory()->create(['community_id' => $community->id]);
    $space = Space::factory()->inGroup($group)->create();
    $chat = Space::factory()->inGroup($group)->create(['type' => 'chat']);
    $resources = Space::factory()->inGroup($group)->create(['type' => 'resources']);

    $post = Post::factory()->inSpace($space)->create(['author_id' => $member->id, 'title' => 'My tomato post', 'body' => 'How I grow tomatoes']);
    $otherPost = Post::factory()->inSpace($space)->create(['author_id' => $other->id, 'title' => 'Title of the other post', 'body' => 'Body written by the other member']);
    Comment::factory()->onPost($otherPost)->create(['author_id' => $member->id, 'body' => 'Nice words from Marie']);
    Comment::factory()->onPost($post)->create(['author_id' => $other->id, 'body' => 'A comment of the other member']);
    ChatMessage::factory()->inSpace($chat)->create(['author_id' => $member->id, 'body' => 'Hello from Marie in the chat']);
    ChatMessage::factory()->inSpace($chat)->create(['author_id' => $other->id, 'body' => 'Chat words of the other member']);
    (new Reaction(['community_id' => $community->id, 'user_id' => $member->id, 'emoji' => '👍']))->forceFill(['post_id' => $otherPost->id])->save();
    CommunityResource::factory()->create(['space_id' => $resources->id, 'curator_id' => $profile->id, 'name' => 'Seed catalog']);

    Storage::disk('public')->put('uploads/tomato.png', 'UPLOAD-BYTES');
    $upload = Upload::create([
        'community_id' => $community->id, 'user_id' => $member->id, 'disk' => 'public', 'path' => 'uploads/tomato.png',
        'mime_type' => 'image/png', 'width' => 10, 'height' => 10, 'size_bytes' => 12, 'alt' => 'A tomato',
    ]);

    $avatarPath = 'avatars/'.$member->cuid.'/face.png';
    Storage::disk(UpdateAvatar::disk())->put($avatarPath, 'AVATAR-BYTES');
    $member->forceFill(['avatar_path' => $avatarPath])->save();

    $member->blockedUsers()->attach($other);

    $newToken = $member->createToken('Backup script', ['read']);
    DB::table('sessions')->insert([
        'id' => 'session-of-marie', 'user_id' => $member->id, 'ip_address' => '203.0.113.7', 'user_agent' => 'Firefox on Linux',
        'payload' => 'SECRET-SESSION-PAYLOAD', 'last_activity' => now()->getTimestamp(),
    ]);

    $conversation = Conversation::create(['community_id' => $community->id, 'created_by' => $member->id]);
    $conversation->participants()->attach([$member->id, $other->id]);
    foreach ([[$member, 'Marie private words'], [$other, 'Private words of the other side']] as [$author, $body]) {
        ChatMessage::factory()->state(['space_id' => null, 'conversation_id' => $conversation->id, 'community_id' => $community->id, 'author_id' => $author->id, 'body' => $body])->create();
    }

    return ['member' => $member, 'other' => $other, 'community' => $community, 'upload' => $upload, 'post' => $post, 'token' => $newToken->plainTextToken, 'tokenHash' => $newToken->accessToken->token];
}

/**
 * Runs the command and reads the zip file that it prints.
 *
 * @return array<string, string> the content of each file of the zip, by name
 */
function runUserExport(User $user): array
{
    $status = Artisan::call('users:export', ['email' => $user->email]);
    expect($status)->toBe(0);

    $path = trim(Artisan::output());
    expect($path)->toEndWith('.zip')->and(file_exists($path))->toBeTrue();

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $files = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $files[(string) $zip->getNameIndex($index)] = (string) $zip->getFromIndex($index);
    }
    $zip->close();

    return $files;
}

test('the export holds the account, the profile, the membership, the content and the files of the member', function () {
    $f = userExportFixture();

    $files = runUserExport($f['member']);
    $account = json_decode($files['account.json'], true);
    $memberships = json_decode($files['memberships.json'], true);
    $activity = json_decode($files['activity.json'], true);

    expect(array_keys($files))->toContain('README.txt', 'account.json', 'memberships.json', 'activity.json', 'posts.json', 'comments.json', 'pages.json', 'chat-messages.json', 'direct-messages.json', 'reactions.json', 'resources.json', 'uploads.json')
        ->and($account['format_version'])->toBe(1)
        ->and($account['account']['cuid'])->toBe($f['member']->cuid)
        ->and($account['account']['email'])->toBe('member@example.com')
        ->and($account['account']['name'])->toBe('Marie Member')
        ->and($account['account']['email_verified_at'])->not->toBeNull()
        ->and($account['account'])->not->toHaveKey('session_version')
        ->and($account['account']['two_factor_authentication'])->toBeFalse()
        ->and($account['account']['show_online_status'])->toBeTrue()
        ->and($account['account']['avatar'])->toBe('files/avatar.png')
        ->and($account['profile']['headline'])->toBe('Grows tomatoes on a balcony')
        ->and($account['profile']['bio'])->toBe('I grow tomatoes')
        ->and($account['profile']['location'])->toBe('Brussels')
        ->and($account['profile']['phone'])->toBe('+32 000 00 00')
        ->and($account['profile']['field_visibility'])->toBeArray()->toHaveKey('phone')->toHaveKey('headline')
        ->and($memberships['memberships'])->toHaveCount(1)
        ->and($memberships['memberships'][0]['community'])->toBe($f['community']->cuid)
        ->and($memberships['memberships'][0]['community_slug'])->toBe($f['community']->slug)
        ->and($memberships['memberships'][0]['role'])->toBe('moderator')
        ->and($memberships['invitations'][0]['email'])->toBe('member@example.com')
        ->and($activity['blocked_members'][0]['user'])->toBe($f['other']->cuid)
        ->and($activity['blocked_members'][0]['username'])->toBe($f['other']->username)
        ->and($activity['api_tokens'][0]['name'])->toBe('Backup script')
        ->and($activity['api_tokens'][0]['abilities'])->toBe(['read'])
        ->and($activity['sessions'][0]['ip_address'])->toBe('203.0.113.7')
        ->and($activity['sessions'][0]['browser'])->toBe('Firefox on Linux');

    $posts = json_decode($files['posts.json'], true)['posts'];
    $comments = json_decode($files['comments.json'], true)['comments'];
    expect($posts)->toHaveCount(1)
        ->and($posts[0]['cuid'])->toBe($f['post']->cuid)
        ->and($posts[0]['body'])->toBe('How I grow tomatoes')
        ->and($posts[0]['community'])->toBe($f['community']->cuid)
        ->and($posts[0])->toHaveKeys(['space', 'space_slug'])
        ->and($comments)->toHaveCount(1)
        ->and($comments[0]['body'])->toBe('Nice words from Marie')
        ->and($comments[0]['post']['title'])->toBe('Title of the other post')
        ->and($files['chat-messages.json'])->toContain('Hello from Marie in the chat')
        ->and($files['reactions.json'])->toContain('👍')
        ->and($files['resources.json'])->toContain('Seed catalog');

    $direct = json_decode($files['direct-messages.json'], true)['direct_messages'];
    expect($direct)->toHaveCount(1)
        ->and($direct[0]['body'])->toBe('Marie private words')
        ->and($direct[0]['conversation'])->not->toBeNull();

    $uploads = json_decode($files['uploads.json'], true)['uploads'];
    $entry = 'files/uploads/'.$f['upload']->cuid.'-tomato.png';
    expect($uploads[0]['file'])->toBe($entry)
        ->and($uploads[0]['alt'])->toBe('A tomato')
        ->and($files[$entry])->toBe('UPLOAD-BYTES')
        ->and($files['files/avatar.png'])->toBe('AVATAR-BYTES');
});

test('the export has nothing that another member wrote, no secret and no internal id', function () {
    $f = userExportFixture();

    $files = runUserExport($f['member']);
    $all = implode("\n", $files);

    expect($all)->not->toContain('Body written by the other member')
        ->and($all)->not->toContain('A comment of the other member')
        ->and($all)->not->toContain('Chat words of the other member')
        ->and($all)->not->toContain('Private words of the other side')
        ->and($all)->not->toContain($f['member']->password)
        ->and($all)->not->toContain('"password"')
        ->and($all)->not->toContain('two_factor_secret')
        ->and($all)->not->toContain('two_factor_recovery_codes')
        ->and($all)->not->toContain('remember_token')
        ->and($all)->not->toContain('body_html')
        ->and($all)->not->toContain('SECRET-SESSION-PAYLOAD')
        ->and($all)->not->toContain(explode('|', $f['token'])[1])
        ->and($all)->not->toContain($f['tokenHash'])
        ->and($all)->not->toContain('other@example.com');

    foreach ($files as $name => $content) {
        if (str_ends_with($name, '.json')) {
            expect(json_decode($content, true))->toBeArray()->and($content)->not->toMatch('/"(id|owner_id|community_id|user_id|post_id|space_id|author_id|curator_id|last_read_message_id)":/');
        }
    }
});

test('the export is one zip file under exports/ of the local disk, named after the username', function () {
    $f = userExportFixture();
    // The file name holds the current second: keep the clock still, or a slow run fails (#1471)
    $this->freezeTime();

    Artisan::call('users:export', ['email' => 'member@example.com']);
    $path = trim(Artisan::output());

    expect(Storage::disk('local')->files(ExportUser::DIRECTORY))->toHaveCount(1)
        ->and(basename($path))->toBe('user-'.$f['member']->username.'-'.now()->format('Ymd-His').'.zip')
        ->and($path)->toBe(Storage::disk('local')->path(ExportUser::DIRECTORY.'/'.basename($path)));
});

test('the export is logged with the cuid of the member, not with the content', function () {
    $f = userExportFixture();
    Log::spy();

    runUserExport($f['member']);

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => $message === 'User exported' && $context === ['user' => $f['member']->cuid])->once();
});

test('the command says when no account has the email address, and writes nothing', function () {
    Storage::fake('local');

    $this->artisan('users:export', ['email' => 'nobody@example.com'])->expectsOutputToContain('No account has this email address.')->assertFailed();

    expect(Storage::disk('local')->files(ExportUser::DIRECTORY))->toBeEmpty();
});

test('a member without a profile, avatar or content gets an export too', function () {
    Storage::fake('local');
    $user = User::factory()->create(['email' => 'empty@example.com']);

    $files = runUserExport($user);
    $account = json_decode($files['account.json'], true);

    expect($account['account']['avatar'])->toBeNull()
        ->and($account['profile'])->toBe([])
        ->and(json_decode($files['posts.json'], true)['posts'])->toBe([]);
});

test('the export of a member without a headline holds the key with null, and the choice for it (#1688)', function () {
    Storage::fake('local');
    $user = User::factory()->withUserProfile()->create();
    $user->profile->forceFill(['field_visibility' => ['headline' => false]])->save();

    $account = json_decode(runUserExport($user)['account.json'], true);

    expect($account['format_version'])->toBe(1)
        ->and($account['profile'])->toHaveKey('headline')
        ->and($account['profile']['headline'])->toBeNull()
        ->and($account['profile']['field_visibility']['headline'])->toBeFalse()
        ->and(array_search('headline', array_keys($account['profile']), true))->toBe(array_search('username', array_keys($account['profile']), true) + 1);
});

test('retention:prune deletes an old user export and keeps a recent one', function () {
    Storage::fake('local');
    foreach (['user-old-20260101-000000.zip' => 8, 'user-new-20260101-000000.zip' => 6] as $name => $days) {
        Storage::disk('local')->put('exports/'.$name, 'zip');
        touch(Storage::disk('local')->path('exports/'.$name), now()->subDays($days)->getTimestamp());
    }

    $this->artisan('retention:prune')->assertSuccessful();

    expect(Storage::disk('local')->exists('exports/user-old-20260101-000000.zip'))->toBeFalse()
        ->and(Storage::disk('local')->exists('exports/user-new-20260101-000000.zip'))->toBeTrue();
});

test('the export action is behind its contract', function () {
    expect(app(ExportsUsers::class))->toBeInstanceOf(ExportUser::class);
});

test('the export lists the comments of the member on events with the cuid and the title of the event (#1516)', function () {
    $f = userExportFixture();
    $events = Space::factory()->inGroup(SpaceGroup::query()->where('community_id', $f['community']->id)->firstOrFail())->create(['type' => 'events']);
    $event = Event::factory()->inSpace($events)->hostedBy($f['other'])->create(['title' => 'Harvest office hour']);
    $comment = Comment::factory()->onEvent($event)->create(['author_id' => $f['member']->id, 'body' => 'Thank you for the harvest tips']);

    $files = runUserExport($f['member']);
    $row = collect(json_decode($files['comments.json'], true)['comments'])->firstWhere('cuid', $comment->cuid);

    expect($row)->toMatchArray([
        'post' => null,
        'resource' => null,
        'event' => ['cuid' => $event->cuid, 'title' => 'Harvest office hour'],
        'space' => $events->cuid,
        'body' => 'Thank you for the harvest tips',
    ])->and($files['README.txt'])->toContain('your comments (Markdown) on posts, resources and events');
});

test('the export lists the notifications of the member without any text or name of another member (#1474)', function () {
    $f = userExportFixture();
    $replier = $f['other'];
    $post = Post::query()->where('author_id', $f['member']->id)->firstOrFail();
    $comment = Comment::factory()->onPost($post)->create(['author_id' => $replier->id, 'body' => 'Secret words of the replier']);
    Notification::factory()->forComment($comment, $f['member'], $replier)->create(['kind' => 'post_comment', 'seen_at' => now()]);

    $files = runUserExport($f['member']);
    $activity = json_decode($files['activity.json'], true);

    expect($activity['notifications'])->toHaveCount(1)
        ->and($activity['notifications'][0]['kind'])->toBe('post_comment')
        ->and($activity['notifications'][0]['community'])->toBe($f['community']->cuid)
        ->and($activity['notifications'][0]['comment'])->toBe($comment->cuid)
        ->and($activity['notifications'][0]['post'])->toBeNull()
        ->and($activity['notifications'][0]['seen_at'])->not->toBeNull()
        ->and($activity['notifications'][0]['read_at'])->toBeNull()
        ->and($activity['notifications'][0])->not->toHaveKey('actor')
        ->and(json_encode($activity['notifications']))->not->toContain('Secret words')->not->toContain($replier->username)->not->toContain($replier->name)
        ->and($files['README.txt'])->toContain('your notifications');
});

test('the export lists the follows and the levels of the member without any text or name of another member (#1475)', function () {
    $f = userExportFixture();
    $other = $f['other'];
    $mine = Post::query()->where('author_id', $f['member']->id)->firstOrFail();
    $theirs = Post::query()->where('author_id', $other->id)->firstOrFail();
    $chat = Space::query()->where('community_id', $f['community']->id)->where('type', 'chat')->firstOrFail();
    $space = Space::query()->where('community_id', $f['community']->id)->where('type', 'discussion')->firstOrFail();
    $root = ChatMessage::factory()->inSpace($chat)->create(['author_id' => $other->id]);
    NotificationFollow::query()->create(['user_id' => $f['member']->id, 'community_id' => $f['community']->id, 'post_id' => $mine->id, 'state' => 'following', 'reason' => 'wrote']);
    NotificationFollow::query()->create(['user_id' => $f['member']->id, 'community_id' => $f['community']->id, 'post_id' => $theirs->id, 'state' => 'muted', 'reason' => 'manual']);
    NotificationFollow::query()->create(['user_id' => $f['member']->id, 'community_id' => $f['community']->id, 'chat_message_id' => $root->id, 'state' => 'stopped', 'reason' => 'manual']);
    NotificationFollow::query()->create(['user_id' => $other->id, 'community_id' => $f['community']->id, 'post_id' => $mine->id, 'state' => 'following', 'reason' => 'manual']);
    NotificationSpaceLevel::query()->create(['user_id' => $f['member']->id, 'space_id' => $space->id, 'level' => 'none']);
    NotificationSpaceLevel::query()->create(['user_id' => $other->id, 'space_id' => $space->id, 'level' => 'all']);

    $files = runUserExport($f['member']);
    $activity = json_decode($files['activity.json'], true);

    expect($activity['notification_follows'])->toHaveCount(3)
        ->and(collect($activity['notification_follows'])->firstWhere('post', $mine->cuid))->toMatchArray(['state' => 'following', 'reason' => 'wrote', 'community' => $f['community']->cuid, 'chat_message' => null])
        ->and(collect($activity['notification_follows'])->firstWhere('post', $theirs->cuid)['state'])->toBe('muted')
        ->and(collect($activity['notification_follows'])->firstWhere('chat_message', $root->cuid))->toMatchArray(['state' => 'stopped', 'post' => null])
        ->and($activity['notification_levels'])->toHaveCount(1)
        ->and($activity['notification_levels'][0])->toMatchArray(['level' => 'none', 'community' => $f['community']->cuid, 'space' => $space->cuid])
        ->and(json_encode([$activity['notification_follows'], $activity['notification_levels']]))->not->toContain($other->username)->not->toContain($other->name)->not->toContain('Body written by the other member')
        ->and($files['README.txt'])->toContain('the threads that you follow or muted');
});

test('the export lists every member tag of the member, of both visibilities, and no other person (#1389)', function () {
    $f = userExportFixture();
    $shown = MemberTag::factory()->forMembers()->create(['community_id' => $f['community']->id, 'name' => 'Qxexport Founder', 'color' => 'purple']);
    $hidden = MemberTag::factory()->forManagers()->create(['community_id' => $f['community']->id, 'name' => 'Qxexport Onboarded']);
    $foreign = MemberTag::factory()->forMembers()->create(['community_id' => $f['community']->id, 'name' => 'Qxexport Other only']);
    MemberTagAssignments::add($shown, $f['member']);
    MemberTagAssignments::add($hidden, $f['member']);
    MemberTagAssignments::add($shown, $f['other']);
    MemberTagAssignments::add($foreign, $f['other']);

    $files = runUserExport($f['member']);
    $memberships = json_decode($files['memberships.json'], true);
    $tags = collect($memberships['member_tags'])->keyBy('name');

    expect(array_keys($memberships))->toContain('member_tags')
        ->and(array_search('member_tags', array_keys($memberships), true))->toBe(array_search('access_groups', array_keys($memberships), true) + 1)
        ->and($tags->keys()->sort()->values()->all())->toBe(['Qxexport Founder', 'Qxexport Onboarded'])
        ->and($tags['Qxexport Onboarded']['visibility'])->toBe('managers')
        ->and($tags['Qxexport Founder']['visibility'])->toBe('members')
        ->and($tags['Qxexport Founder']['cuid'])->toBe($shown->cuid)
        ->and($tags['Qxexport Founder']['community'])->toBe($f['community']->cuid)
        ->and($tags['Qxexport Founder']['community_slug'])->toBe($f['community']->slug)
        ->and($tags['Qxexport Founder']['tagged_at'])->not->toBeNull()
        ->and(array_keys($tags['Qxexport Founder']))->toBe(['cuid', 'name', 'visibility', 'community', 'community_slug', 'tagged_at'])
        ->and(json_encode($memberships['member_tags']))->not->toContain($f['other']->username)->not->toContain($f['other']->cuid)->not->toContain('Other only')
        ->and($files['README.txt'])->toContain('the tags that the managers of a community put on you');
});

test('the export lists the invitations sent to the address, with their groups and tags by name, for an address with capitals too (#1390)', function () {
    $f = userExportFixture();
    $member = $f['member'];
    $member->forceFill(['email' => 'Member@Example.com'])->save();
    $group = AccessGroup::query()->create(['community_id' => $f['community']->id, 'name' => 'Qxexport Paid']);
    $hidden = MemberTag::factory()->forManagers()->create(['community_id' => $f['community']->id, 'name' => 'Qxexport Onboarded']);
    $invitation = $f['community']->communityInvitations()->sole();
    InvitationGrants::attach($invitation, [$group], [$hidden]);
    $f['community']->communityInvitations()->create(['email' => 'someone-else@example.com', 'role' => 'member']);

    $files = runUserExport($member);
    $invitations = json_decode($files['memberships.json'], true)['invitations'];

    expect($invitations)->toHaveCount(1)
        ->and($invitations[0]['email'])->toBe('member@example.com')
        ->and($invitations[0]['access_groups'])->toBe([['cuid' => $group->cuid, 'name' => 'Qxexport Paid']])
        ->and($invitations[0]['member_tags'])->toBe([['cuid' => $hidden->cuid, 'name' => 'Qxexport Onboarded']])
        ->and($files['README.txt'])->toContain('the invitations sent to your email address (with the access groups and the member tags that each gives when you join, and whether our mail provider reported that the invitation email could not be delivered)');
});

test('the export lists the rows of the imports of invitations with the address of the member, in any case, and nothing of another person (#1390)', function () {
    $f = userExportFixture();
    $f['member']->forceFill(['email' => 'Member@Example.com'])->save();
    $import = InvitationImport::factory()->done()->create(['community_id' => $f['community']->id, 'user_id' => $f['other']->id]);
    InvitationImportRow::factory()->outcome(KnowiiInvitationImportOutcome::Sent)->create(['invitation_import_id' => $import->id, 'row_number' => 2, 'email' => 'member@example.com', 'sent_at' => now()]);
    InvitationImportRow::factory()->create(['invitation_import_id' => $import->id, 'row_number' => 3, 'email' => 'someone-else@example.com']);

    $files = runUserExport($f['member']);
    $memberships = json_decode($files['memberships.json'], true);
    $keys = array_keys($memberships);
    $rows = $memberships['invitation_import_rows'];

    expect(array_search('invitation_import_rows', $keys, true))->toBe(array_search('invitations', $keys, true) + 1)
        ->and($rows)->toHaveCount(1)
        ->and($rows[0]['email'])->toBe('member@example.com')
        ->and($rows[0]['outcome'])->toBe('sent')
        ->and($rows[0]['community'])->toBe($f['community']->cuid)
        ->and(array_keys($rows[0]))->toBe(['community', 'community_slug', 'email', 'outcome', 'reason', 'created_at', 'sent_at'])
        ->and(json_encode($rows))->not->toContain('someone-else')->not->toContain($f['other']->cuid)
        ->and($files['README.txt'])->toContain('the rows of the imports of invitations from a file with your address');
});

test('the export lists the invitation emails that the account sent, with the community and the time, and none of another account (#1390)', function () {
    $f = userExportFixture();
    DB::table('invitation_mails')->insert([
        ['community_id' => $f['community']->id, 'user_id' => $f['member']->id, 'created_at' => '2026-11-18 10:00:00'],
        // The community is gone: the row stays for the cap of the account
        ['community_id' => null, 'user_id' => $f['member']->id, 'created_at' => '2026-11-18 11:00:00'],
        ['community_id' => $f['community']->id, 'user_id' => $f['other']->id, 'created_at' => '2026-11-18 12:00:00'],
    ]);

    $files = runUserExport($f['member']);
    $activity = json_decode($files['activity.json'], true);

    expect($activity['invitation_mails'])->toBe([
        ['community' => $f['community']->cuid, 'created_at' => '2026-11-18T10:00:00+00:00'],
        ['community' => null, 'created_at' => '2026-11-18T11:00:00+00:00'],
    ])
        ->and($files['README.txt'])->toContain('your sessions, the invitation emails that you sent in the last days (the community and the time), and the record of each email that we sent you');
});

test('the export lists the joins of the member through an invite link, with the name of the link, after the join blocks, and never its token (#1391)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23T10:00:00Z'));
    $f = userExportFixture();
    $link = InviteLink::factory()->create(['community_id' => $f['community']->id, 'name' => 'Qxexport Course buyers']);
    $foreign = InviteLink::factory()->create(['community_id' => $f['community']->id, 'name' => 'Qxexport Other link']);
    InviteLinkUse::query()->create(['invite_link_id' => $link->id, 'user_id' => $f['member']->id]);
    InviteLinkUse::query()->create(['invite_link_id' => $foreign->id, 'user_id' => $f['other']->id]);

    $files = runUserExport($f['member']);
    $memberships = json_decode($files['memberships.json'], true);
    $keys = array_keys($memberships);

    expect(array_search('invite_link_uses', $keys, true))->toBe(array_search('join_blocks', $keys, true) + 1)
        ->and($memberships['invite_link_uses'])->toBe([[
            'community' => $f['community']->cuid,
            'community_slug' => $f['community']->slug,
            'invite_link' => $link->cuid,
            'invite_link_name' => 'Qxexport Course buyers',
            'joined_at' => '2026-11-23T10:00:00+00:00',
        ]])
        ->and(implode("\n", $files))->not->toContain($link->token)->not->toContain($link->token_hash)->not->toContain('Qxexport Other link')
        ->and($files['README.txt'])->toContain('the invite links through which you joined a community');
});
