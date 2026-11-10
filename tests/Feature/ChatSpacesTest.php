<?php

use App\ApiRoutes;
use App\Contracts\Uploads\UploadsImages;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\ChatMessage;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->c = communityWithMembers();
    $this->space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Chat, 'name' => 'General', 'posting_policy' => KnowiiSpacePostingPolicy::Members]);
});

function chatPath(Space $space, ?ChatMessage $message = null, string $suffix = ''): string
{
    $params = [ApiRoutes::PARAM_COMMUNITY => $space->community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid];
    if ($message !== null) {
        $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_MESSAGE, [...$params, ApiRoutes::PARAM_MESSAGE => $message->cuid]);

        return $suffix === '' ? $path : $path.'/'.$suffix;
    }

    return ApiRoutes::path($suffix === 'read' ? ApiRoutes::COMMUNITY_SPACE_MESSAGES_READ : ApiRoutes::COMMUNITY_SPACE_MESSAGES, $params);
}

test('two members chat in a chat space and in a thread, with live events (#1185)', function () {
    Event::fake([SpaceContentChanged::class]);

    $hello = $this->actingAs($this->c['member'])->postJson(chatPath($this->space), ['body' => 'Hello @'.$this->c['admin']->profile->username])
        ->assertCreated()
        ->assertJsonPath('data.reply_count', 0)
        ->assertJsonPath('data.author.cuid', $this->c['member']->cuid)
        ->json('data');
    $first = ChatMessage::query()->where('cuid', $hello['cuid'])->firstOrFail();
    expect($first->mentions()->count())->toBe(1);

    $this->actingAs($this->c['admin'])->postJson(chatPath($this->space), ['body' => 'A reply in the thread', 'parent_cuid' => $first->cuid])
        ->assertCreated()
        ->assertJsonPath('data.parent_cuid', $first->cuid);

    expect($first->refresh()->reply_count)->toBe(1)
        ->and($first->last_reply_at)->not->toBeNull();
    Event::assertDispatched(SpaceContentChanged::class, fn (SpaceContentChanged $event) => $event->name === SpaceContentChanged::MESSAGE_CREATED
        && $event->payload['parent_cuid'] === $first->cuid
        && ! array_key_exists('body', $event->payload));

    // The feed carries the first-level messages; the thread carries the replies
    $feed = $this->actingAs($this->c['admin'])->getJson(chatPath($this->space))->assertOk();
    expect($feed->json('data.*.cuid'))->toBe([$first->cuid])
        ->and($feed->json('data.0.reply_count'))->toBe(1);
    $thread = $this->actingAs($this->c['member'])->getJson(chatPath($this->space, $first, 'thread'))->assertOk();
    expect($thread->json('data.replies.0.body'))->toBe('A reply in the thread');
});

test('threads have one level', function () {
    $first = ChatMessage::factory()->inSpace($this->space)->create();
    $reply = ChatMessage::factory()->replyTo($first)->create();

    $this->actingAs($this->c['member'])->postJson(chatPath($this->space), ['body' => 'Deeper', 'parent_cuid' => $reply->cuid])
        ->assertStatus(422)
        ->assertJsonPath('type', 'businessIssue');
});

test('the feed pages back with a cursor, oldest first in each page (ADR 0011)', function () {
    $messages = ChatMessage::factory()->inSpace($this->space)->count(55)->create();

    $newest = $this->actingAs($this->c['member'])->getJson(chatPath($this->space))->assertOk();
    expect($newest->json('data'))->toHaveCount(50)
        ->and($newest->json('metadata.has_more'))->toBeTrue()
        ->and($newest->json('data.49.cuid'))->toBe($messages[54]->cuid);

    $older = $this->actingAs($this->c['member'])->getJson(chatPath($this->space).'?before='.$newest->json('data.0.cuid'))->assertOk();
    expect($older->json('data.*.cuid'))->toBe($messages->take(5)->pluck('cuid')->all())
        ->and($older->json('metadata.has_more'))->toBeFalse();
});

test('the author edits and deletes their message; it stays as a marker (#1185)', function () {
    $message = ChatMessage::factory()->inSpace($this->space)->create(['author_id' => $this->c['member']->id]);

    $this->actingAs($this->c['member'])->patchJson(chatPath($this->space, $message), ['body' => '**Edited**'])
        ->assertOk()
        ->assertJsonPath('data.body_html', '<p><strong>Edited</strong></p>')
        ->assertJsonPath('data.edited_at', fn ($value) => $value !== null);

    $this->actingAs($this->c['admin'])->patchJson(chatPath($this->space, $message), ['body' => 'Not mine'])->assertForbidden();

    $this->actingAs($this->c['member'])->deleteJson(chatPath($this->space, $message))->assertOk();
    $this->actingAs($this->c['member'])->getJson(chatPath($this->space, $message))
        ->assertOk()
        ->assertJsonPath('data.body', null)
        ->assertJsonPath('data.author', null)
        ->assertJsonPath('data.deleted_at', fn ($value) => $value !== null);
});

test('moderators delete any message; other members do not (#1185)', function (string $who, bool $allowed) {
    $message = ChatMessage::factory()->inSpace($this->space)->create(['author_id' => $this->c['owner']->id]);

    $response = $this->actingAs($this->c[$who])->deleteJson(chatPath($this->space, $message));

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'admin' => ['admin', true],
    'moderator with delete_content' => ['moderator', true],
    'member' => ['member', false],
    'outsider' => ['outsider', false],
]);

test('members react to messages (#1176)', function () {
    $message = ChatMessage::factory()->inSpace($this->space)->create();

    $this->actingAs($this->c['member'])->postJson(chatPath($this->space, $message, 'reactions'), ['emoji' => '👍'])
        ->assertOk()
        ->assertJsonPath('data.0.emoji', '👍')
        ->assertJsonPath('data.0.reacted', true);

    $this->actingAs($this->c['outsider'])->postJson(chatPath($this->space, $message, 'reactions'), ['emoji' => '👍'])->assertForbidden();
});

test('the unread markers follow what each member read (#1185)', function () {
    $messages = ChatMessage::factory()->inSpace($this->space)->count(3)->create(['author_id' => $this->c['admin']->id]);

    $this->actingAs($this->c['member'])->postJson(chatPath($this->space, suffix: 'read'), ['message_cuid' => $messages[0]->cuid])->assertOk();

    $this->actingAs($this->c['member'])->get(route('spaces.show', [
        'communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->space->slug,
    ]))->assertOk()->assertInertia(fn ($page) => $page
        ->where('chat.first_unread_cuid', $messages[1]->cuid)
        ->has('chat.messages', 3)
        ->where('spaceGroups.0.spaces.0.unread_count', 2));

    // The marker never goes back
    $this->actingAs($this->c['member'])->postJson(chatPath($this->space, suffix: 'read'), ['message_cuid' => $messages[2]->cuid])->assertOk();
    $this->actingAs($this->c['member'])->postJson(chatPath($this->space, suffix: 'read'), ['message_cuid' => $messages[0]->cuid])->assertOk();
    $this->actingAs($this->c['member'])->get(route('spaces.show', [
        'communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->space->slug,
    ]))->assertInertia(fn ($page) => $page->where('chat.first_unread_cuid', null)->where('spaceGroups.0.spaces.0.unread_count', 0));
});

test('the members who cannot read a space never read its messages (access groups, #1241)', function () {
    $this->space->update(['visibility' => KnowiiSpaceVisibility::Groups]);
    $message = ChatMessage::factory()->inSpace($this->space)->create();

    $this->actingAs($this->c['member'])->getJson(chatPath($this->space))->assertForbidden();
    $this->actingAs($this->c['member'])->getJson(chatPath($this->space, $message))->assertForbidden();
    $this->actingAs($this->c['member'])->postJson(chatPath($this->space), ['body' => 'Hi'])->assertForbidden();
});

test('only the posting policy decides who writes', function () {
    $this->space->update(['posting_policy' => KnowiiSpacePostingPolicy::Moderators]);

    $this->actingAs($this->c['member'])->postJson(chatPath($this->space), ['body' => 'Hi'])->assertForbidden();
    $this->actingAs($this->c['moderator'])->postJson(chatPath($this->space), ['body' => 'Hi'])->assertCreated();
});

test('an image used only by a message survives uploads:prune (#1201)', function () {
    $upload = app(UploadsImages::class)->upload($this->c['member'], $this->c['community'], [
        'file' => UploadedFile::fake()->image('photo.jpg', 40, 40),
        'alt' => 'A photo',
    ]);
    $this->actingAs($this->c['member'])->postJson(chatPath($this->space), ['body' => "![A photo]({$upload->url()})"])->assertCreated();
    Upload::query()->whereKey($upload->id)->update(['created_at' => now()->subDays(30)]);

    $this->artisan('uploads:prune')->assertSuccessful();

    expect(Upload::query()->whereKey($upload->id)->exists())->toBeTrue();
});

test('the raw HTML of a message is escaped (ADR 0013)', function () {
    $this->actingAs($this->c['member'])->postJson(chatPath($this->space), ['body' => 'Hi <img src=x onerror=alert(1)>'])
        ->assertCreated()
        ->assertJsonPath('data.body_html', '<p>Hi &lt;img src=x onerror=alert(1)&gt;</p>');
});
