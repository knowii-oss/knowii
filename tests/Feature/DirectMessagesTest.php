<?php

use App\ApiRoutes;
use App\Broadcasting\ConversationPresenceChannel;
use App\Constants;
use App\Contracts\Uploads\UploadsImages;
use App\Events\Conversations\ConversationChanged;
use App\Models\ChatMessage;
use App\Models\Community;
use App\Models\Conversation;
use App\Models\Mention;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
 * Direct messages (#1186, ADR 0028)
 */

beforeEach(function () {
    Storage::fake('public');
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
});

function conversationsPath(?Community $community = null): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_CONVERSATIONS, [ApiRoutes::PARAM_COMMUNITY => ($community ?? test()->community)->cuid]);
}

function conversationPath(string $conversationCuid, string $route = ApiRoutes::COMMUNITY_CONVERSATION, ?string $messageCuid = null): string
{
    return ApiRoutes::path($route, [
        ApiRoutes::PARAM_COMMUNITY => test()->community->cuid,
        ApiRoutes::PARAM_CONVERSATION => $conversationCuid,
        ApiRoutes::PARAM_MESSAGE => $messageCuid ?? '',
    ]);
}

/**
 * @param  list<User>  $others
 */
function writeTo(User $from, array $others, string $body = 'Hello'): string
{
    return test()->actingAs($from)
        ->postJson(conversationsPath(), ['participant_cuids' => array_map(fn (User $user) => $user->cuid, $others), 'body' => $body])
        ->assertCreated()
        ->json('data.cuid');
}

function unreadTotal(User $user): int
{
    app('auth')->forgetGuards();

    return test()->actingAs($user)->getJson(conversationsPath())->assertOk()->json('metadata.unread_total');
}

test('two members share one one-to-one conversation, and see what they did not read', function () {
    $first = writeTo($this->c['member'], [$this->c['admin']], 'Hello Admin');

    $this->actingAs($this->c['member'])->getJson(conversationPath($first))
        ->assertOk()
        ->assertJsonPath('data.is_direct', true)
        ->assertJsonCount(2, 'data.participants')
        ->assertJsonPath('data.unread_count', 0)
        ->assertJsonPath('data.last_message.text', 'Hello Admin');

    expect(unreadTotal($this->c['admin']))->toBe(1);

    // Writing again to the same member continues the conversation, from either side
    expect(writeTo($this->c['admin'], [$this->c['member']], 'Hi Member'))->toBe($first)
        ->and(Conversation::count())->toBe(1)
        ->and(unreadTotal($this->c['member']))->toBe(1);
});

test('a group is a new conversation each time, with 2 to 10 members of the community', function () {
    $group = writeTo($this->c['owner'], [$this->c['admin'], $this->c['member']]);
    $again = writeTo($this->c['owner'], [$this->c['admin'], $this->c['member']]);

    expect($group)->not->toBe($again);

    $this->actingAs($this->c['owner']);
    $this->postJson(conversationsPath(), ['participant_cuids' => [$this->c['outsider']->cuid], 'body' => 'Hi'])
        ->assertStatus(400)
        ->assertJsonStructure(['errors' => ['participant_cuids']]);
    $this->postJson(conversationsPath(), ['participant_cuids' => [$this->c['owner']->cuid], 'body' => 'Hi'])->assertStatus(400);
    $tooMany = User::factory()->count(Conversation::MAX_PARTICIPANTS)->withUserProfile()->create();
    $tooMany->each(fn (User $user) => $this->community->users()->attach($user, ['role' => 'member']));
    $this->postJson(conversationsPath(), ['participant_cuids' => $tooMany->pluck('cuid')->all(), 'body' => 'Hi'])->assertStatus(400);

    $this->actingAs($this->c['outsider'])
        ->postJson(conversationsPath(), ['participant_cuids' => [$this->c['member']->cuid], 'body' => 'Hi'])
        ->assertForbidden();
});

test('only the participants read a conversation; a conversation of another community gives a 404', function () {
    $cuid = writeTo($this->c['member'], [$this->c['admin']]);
    $message = ChatMessage::query()->latest('id')->firstOrFail();

    $this->actingAs($this->c['moderator'])->getJson(conversationPath($cuid))->assertForbidden();
    $this->actingAs($this->c['moderator'])->getJson(conversationPath($cuid, ApiRoutes::COMMUNITY_CONVERSATION_MESSAGES))->assertForbidden();
    $this->actingAs($this->c['moderator'])->postJson(conversationPath($cuid, ApiRoutes::COMMUNITY_CONVERSATION_MESSAGES), ['body' => 'Hi'])->assertForbidden();
    $this->actingAs($this->c['moderator'])->getJson(conversationPath($cuid, ApiRoutes::COMMUNITY_CONVERSATION_MESSAGE, $message->cuid))->assertForbidden();

    $other = communityWithMembers();
    $this->actingAs($this->c['member'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_CONVERSATION, [ApiRoutes::PARAM_COMMUNITY => $other['community']->cuid, ApiRoutes::PARAM_CONVERSATION => $cuid]))
        ->assertNotFound();

    // Space messages and direct messages never mix
    expect($message->space_id)->toBeNull()->and($message->conversation_id)->not->toBeNull();
});

test('a member who leaves the community loses access to its conversations', function () {
    $cuid = writeTo($this->c['member'], [$this->c['admin']]);
    $this->community->users()->detach($this->c['member']);
    $this->app['auth']->forgetGuards();

    $this->actingAs($this->c['member']->refresh())->getJson(conversationPath($cuid))->assertForbidden();
});

test('a block stops one-to-one conversations both ways and keeps the blocked member out of groups', function () {
    $cuid = writeTo($this->c['member'], [$this->c['admin']]);

    $this->actingAs($this->c['admin'])->putJson(ApiRoutes::path(ApiRoutes::USER_BLOCK, [ApiRoutes::PARAM_USER_CUID => $this->c['member']->cuid]))->assertOk();
    $this->actingAs($this->c['admin'])->getJson(ApiRoutes::path(ApiRoutes::USER_BLOCKS))->assertOk()->assertJsonPath('data.0.cuid', $this->c['member']->cuid);

    $this->actingAs($this->c['member'])->postJson(conversationPath($cuid, ApiRoutes::COMMUNITY_CONVERSATION_MESSAGES), ['body' => 'Hi'])->assertForbidden();
    $this->actingAs($this->c['admin'])->postJson(conversationPath($cuid, ApiRoutes::COMMUNITY_CONVERSATION_MESSAGES), ['body' => 'Hi'])->assertForbidden();
    $this->actingAs($this->c['member'])
        ->postJson(conversationsPath(), ['participant_cuids' => [$this->c['admin']->cuid, $this->c['owner']->cuid], 'body' => 'Hi'])
        ->assertStatus(422);
    // The conversation stays readable
    $this->actingAs($this->c['member'])->getJson(conversationPath($cuid, ApiRoutes::COMMUNITY_CONVERSATION_MESSAGES))->assertOk();

    $this->actingAs($this->c['admin'])->deleteJson(ApiRoutes::path(ApiRoutes::USER_BLOCK, [ApiRoutes::PARAM_USER_CUID => $this->c['member']->cuid]))->assertNoContent();
    $this->actingAs($this->c['member'])->postJson(conversationPath($cuid, ApiRoutes::COMMUNITY_CONVERSATION_MESSAGES), ['body' => 'Hi'])->assertCreated();

    $this->actingAs($this->c['member'])->putJson(ApiRoutes::path(ApiRoutes::USER_BLOCK, [ApiRoutes::PARAM_USER_CUID => $this->c['member']->cuid]))->assertStatus(422);
});

test('the owner turns direct messages off: nobody writes, everybody still reads', function () {
    $cuid = writeTo($this->c['member'], [$this->c['admin']]);

    $this->actingAs($this->c['owner'])
        ->patchJson(ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]), ['direct_messages_enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.direct_messages_enabled', false);

    $this->actingAs($this->c['member'])->postJson(conversationsPath(), ['participant_cuids' => [$this->c['owner']->cuid], 'body' => 'Hi'])->assertStatus(422);
    $this->actingAs($this->c['member'])->postJson(conversationPath($cuid, ApiRoutes::COMMUNITY_CONVERSATION_MESSAGES), ['body' => 'Hi'])->assertForbidden();
    $this->actingAs($this->c['member'])->getJson(conversationPath($cuid, ApiRoutes::COMMUNITY_CONVERSATION_MESSAGES))->assertOk()->assertJsonCount(1, 'data');
});

test('participants read, edit, delete and react; the read marker clears the unread count', function () {
    $cuid = writeTo($this->c['member'], [$this->c['admin'], $this->c['owner']], 'First');
    $message = ChatMessage::query()->latest('id')->firstOrFail();
    $messagePath = conversationPath($cuid, ApiRoutes::COMMUNITY_CONVERSATION_MESSAGE, $message->cuid);

    $this->actingAs($this->c['admin'])->patchJson($messagePath, ['body' => 'Changed'])->assertForbidden();
    $this->actingAs($this->c['admin'])->deleteJson($messagePath)->assertForbidden();
    $this->actingAs($this->c['admin'])
        ->postJson(conversationPath($cuid, ApiRoutes::COMMUNITY_CONVERSATION_MESSAGE_REACTIONS, $message->cuid), ['emoji' => '👍'])
        ->assertOk();
    $this->actingAs($this->c['admin'])->postJson(conversationPath($cuid, ApiRoutes::COMMUNITY_CONVERSATION_READ), ['message_cuid' => $message->cuid])->assertOk();
    expect(unreadTotal($this->c['admin']))->toBe(0);

    $this->app['auth']->forgetGuards();
    $this->actingAs($this->c['member'])->patchJson($messagePath, ['body' => 'Changed'])->assertOk()->assertJsonPath('data.body', 'Changed');
    $this->actingAs($this->c['member'])->deleteJson($messagePath)->assertOk();
    $this->actingAs($this->c['member'])->getJson($messagePath)->assertOk()->assertJsonPath('data.body', null);
});

test('a direct message mentions its participants only', function () {
    writeTo($this->c['member'], [$this->c['admin']], "Hi @{$this->c['admin']->username} and @{$this->c['moderator']->username}");

    expect(Mention::query()->pluck('user_id')->all())->toBe([$this->c['admin']->id]);
});

test('an image used only by a direct message survives uploads:prune (#1201)', function () {
    $upload = app(UploadsImages::class)->upload($this->c['member'], $this->community, [
        'file' => UploadedFile::fake()->image('photo.jpg', 40, 40),
        'alt' => 'A photo',
    ]);
    writeTo($this->c['member'], [$this->c['admin']], "![A photo]({$upload->url()})");
    Upload::query()->whereKey($upload->id)->update(['created_at' => now()->subDays(30)]);

    $this->artisan('uploads:prune')->assertSuccessful();

    expect(Upload::query()->whereKey($upload->id)->exists())->toBeTrue();
});

test('a new message goes to the conversation channel and to the channel of each participant, identifiers only', function () {
    Event::fake([ConversationChanged::class]);

    $cuid = writeTo($this->c['member'], [$this->c['admin']]);

    Event::assertDispatched(ConversationChanged::class, function (ConversationChanged $event) use ($cuid) {
        $channels = array_map(fn (PrivateChannel $channel) => $channel->name, $event->broadcastOn());
        $expected = ['private-conversation.'.$cuid, 'private-user.'.$this->c['admin']->cuid, 'private-user.'.$this->c['member']->cuid];
        sort($channels);
        sort($expected);

        return $event->broadcastAs() === ConversationChanged::MESSAGE_CREATED
            && array_keys($event->broadcastWith()) === ['conversation_cuid', 'message_cuid', 'author_cuid']
            && $channels === $expected;
    });
});

test('the channels of a conversation are for its participants, the channel of a member for that member', function () {
    $cuid = writeTo($this->c['member'], [$this->c['admin']]);
    $channels = Broadcast::driver()->getChannels();
    $conversationChannel = $channels->get(Constants::WS_CHANNEL_CONVERSATION);
    $userChannel = $channels->get(Constants::WS_CHANNEL_USER);

    expect($conversationChannel($this->c['admin'], $cuid))->toBeTrue()
        ->and($conversationChannel($this->c['moderator'], $cuid))->toBeFalse()
        ->and($userChannel($this->c['admin'], $this->c['admin']->cuid))->toBeTrue()
        ->and($userChannel($this->c['admin'], $this->c['member']->cuid))->toBeFalse()
        ->and((new ConversationPresenceChannel)->join($this->c['admin'], $cuid))->toBeArray()
        ->and((new ConversationPresenceChannel)->join($this->c['moderator'], $cuid))->toBeFalse();
});

test('direct messages need their own token abilities', function () {
    Sanctum::actingAs($this->c['member'], ['chat:read', 'chat:write']);
    $this->getJson(conversationsPath())->assertForbidden();

    Sanctum::actingAs($this->c['member'], ['messages:read']);
    $this->getJson(conversationsPath())->assertOk();
    $this->postJson(conversationsPath(), ['participant_cuids' => [$this->c['admin']->cuid], 'body' => 'Hi'])->assertForbidden();
});

test('the messages page lists the conversations and opens one for its participants only', function () {
    $cuid = writeTo($this->c['member'], [$this->c['admin']], 'Hello');

    $this->actingAs($this->c['admin'])
        ->get(route('messages.show', ['communitySlug' => $this->community->slug, 'conversationCuid' => $cuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Messages/Index')
            ->has('conversations', 1)
            ->where('conversation.cuid', $cuid)
            ->has('messages.items', 1)
            ->where('permissions.canSend', true)
            ->where('community.unread_direct_messages', 1));

    $this->actingAs($this->c['moderator'])
        ->get(route('messages.show', ['communitySlug' => $this->community->slug, 'conversationCuid' => $cuid]))
        ->assertForbidden();
    $this->actingAs($this->c['outsider'])->get(route('messages.index', ['communitySlug' => $this->community->slug]))->assertForbidden();
});
