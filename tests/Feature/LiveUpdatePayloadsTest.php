<?php

use App\ApiRoutes;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Communities\CommunityCreated;
use App\Events\Communities\CommunityDeleted;
use App\Events\Communities\CommunityUpdated;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Events\CommunityResources\CommunityResourceCreated;
use App\Events\CommunityResources\CommunityResourceDeleted;
use App\Events\CommunityResources\CommunityResourceUpdated;
use App\Events\Conversations\ConversationChanged;
use App\Events\InvitationImports\InvitationImportChanged;
use App\Events\Notifications\NotificationsChanged;
use App\Events\Spaces\SpaceContentChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\CommunityResource;
use App\Models\Conversation;
use App\Models\Event as SpaceEvent;
use App\Models\Page;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
 * A live update carries identifiers only (ADR 0009): the page reads the item through the API, which checks the access of
 * the reader now. This guard walks every class of app/Events that is broadcast, builds each of its payloads, and fails on a
 * key that is not in the list below: a cuid, a kind or a time. A new broadcast event needs its builder here.
 */

/**
 * The key names that a live update may carry
 */
const LIVE_UPDATE_KEYS = [
    // Cuids of the items. Never the cuid of the person who made the change
    'cuid', 'community_cuid', 'space_cuid', 'resource_cuid', 'post_cuid', 'comment_cuid', 'page_cuid', 'event_cuid',
    'message_cuid', 'parent_cuid', 'conversation_cuid', 'notification_cuid', 'import_cuid',
];

/**
 * Every class of app/Events that is broadcast and can be built
 *
 * @return list<class-string>
 */
function liveUpdateClasses(): array
{
    $classes = [];
    foreach (glob(app_path('Events/*/*.php')) ?: [] as $file) {
        $class = 'App\\Events\\'.Str::of($file)->after(app_path('Events').DIRECTORY_SEPARATOR)->beforeLast('.php')->replace('/', '\\')->toString();
        $reflection = new ReflectionClass($class);
        if (! $reflection->isAbstract() && $reflection->implementsInterface(ShouldBroadcast::class)) {
            $classes[] = $class;
        }
    }
    sort($classes);

    return $classes;
}

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
});

/**
 * The events that each broadcast class sends, built as the application builds them
 *
 * @return array<class-string, list<object>>
 */
function liveUpdatesOfEachClass(): array
{
    $c = test()->c;
    $community = $c['community'];
    $space = fn (KnowiiSpaceType $type) => Space::factory()->inGroup(test()->group)->create(['type' => $type]);
    $discussion = $space(KnowiiSpaceType::Discussion);
    $post = Post::factory()->inSpace($discussion)->create(['author_id' => $c['member']->id]);
    $comment = Comment::factory()->onPost($post)->create(['author_id' => $c['member']->id]);
    $resource = CommunityResource::factory()->create(['space_id' => $space(KnowiiSpaceType::Resources)->id, 'community_id' => $community->id]);
    $resourceComment = Comment::factory()->create(['post_id' => null, 'community_resource_id' => $resource->id, 'community_id' => $community->id, 'author_id' => $c['member']->id]);
    $event = SpaceEvent::factory()->inSpace($space(KnowiiSpaceType::Events))->create();
    $eventComment = Comment::factory()->onEvent($event)->create(['author_id' => $c['member']->id]);
    $pages = $space(KnowiiSpaceType::Pages);
    $page = Page::factory()->inSpace($pages)->create();
    $chat = $space(KnowiiSpaceType::Chat);
    $message = ChatMessage::factory()->inSpace($chat)->create(['author_id' => $c['member']->id]);
    $reply = ChatMessage::factory()->replyTo($message)->create(['author_id' => $c['admin']->id]);
    $conversation = Conversation::create(['community_id' => $community->id, 'created_by' => $c['member']->id]);
    $conversation->participants()->attach([$c['member']->id, $c['admin']->id]);
    $direct = ChatMessage::factory()->state(['space_id' => null, 'conversation_id' => $conversation->id, 'community_id' => $community->id, 'author_id' => $c['member']->id])->create();

    return [
        AccessGroupsChanged::class => [AccessGroupsChanged::for($community)],
        CommunityCreated::class => [new CommunityCreated($community)],
        CommunityDeleted::class => [new CommunityDeleted($community)],
        CommunityMembersChanged::class => [CommunityMembersChanged::for($community)],
        CommunityResourceCreated::class => [new CommunityResourceCreated($resource)],
        CommunityResourceDeleted::class => [new CommunityResourceDeleted($resource)],
        CommunityResourceUpdated::class => [new CommunityResourceUpdated($resource)],
        CommunityUpdated::class => [new CommunityUpdated($community)],
        ConversationChanged::class => array_map(
            fn (string $name) => ConversationChanged::forMessage($name, $direct),
            [ConversationChanged::MESSAGE_CREATED, ConversationChanged::MESSAGE_UPDATED, ConversationChanged::MESSAGE_DELETED, ConversationChanged::REACTIONS_CHANGED],
        ),
        InvitationImportChanged::class => [new InvitationImportChanged($c['admin']->cuid, $community->cuid, 'cuidofanimport')],
        NotificationsChanged::class => [new NotificationsChanged($c['member']->cuid, $community->cuid, 'cuidofanotification'), new NotificationsChanged($c['member']->cuid, $community->cuid)],
        SpaceContentChanged::class => [
            SpaceContentChanged::forPost(SpaceContentChanged::POST_DELETED, $post),
            SpaceContentChanged::forPage(SpaceContentChanged::PAGE_DELETED, $page),
            SpaceContentChanged::forPagesOrder($pages),
            SpaceContentChanged::forEvent(SpaceContentChanged::EVENT_DELETED, $event),
            SpaceContentChanged::forEventAnswers($event),
            SpaceContentChanged::forMessage(SpaceContentChanged::MESSAGE_DELETED, $message),
            SpaceContentChanged::forMessage(SpaceContentChanged::MESSAGE_DELETED, $reply),
            SpaceContentChanged::forComment(SpaceContentChanged::COMMENT_DELETED, $comment),
            SpaceContentChanged::forComment(SpaceContentChanged::COMMENT_DELETED, $resourceComment),
            SpaceContentChanged::forComment(SpaceContentChanged::COMMENT_DELETED, $eventComment),
            SpaceContentChanged::forReactions($post),
            SpaceContentChanged::forReactions($comment),
            SpaceContentChanged::forReactions($reply),
        ],
        SpaceTreeChanged::class => [SpaceTreeChanged::for($community)],
    ];
}

test('every broadcast event has a builder in this guard', function () {
    expect(array_keys(liveUpdatesOfEachClass()))->toEqualCanonicalizing(liveUpdateClasses());
});

test('every live update carries identifiers only: each key is a cuid, a kind or a time, and each value is a text or nothing', function () {
    $seen = 0;
    foreach (liveUpdatesOfEachClass() as $class => $events) {
        foreach ($events as $event) {
            // The worker builds the payload: no account in the request
            app('auth')->forgetGuards();
            $payload = $event->broadcastWith();
            $name = $class.' '.(method_exists($event, 'broadcastAs') ? $event->broadcastAs() : '');
            expect(array_diff(array_keys($payload), LIVE_UPDATE_KEYS))->toBe([], $name.' carries a key that is not an identifier');
            foreach ($payload as $key => $value) {
                expect($value === null || is_string($value))->toBeTrue($name.' carries a value that is not a text in '.$key);
            }
            $seen++;
        }
    }
    expect($seen)->toBe(29);
});

test('the payload is the same for every reader: it never depends on the account of the request', function () {
    foreach (liveUpdatesOfEachClass() as $class => $events) {
        foreach ($events as $event) {
            app('auth')->forgetGuards();
            $asWorker = $event->broadcastWith();
            $this->actingAs($this->c['owner']);
            expect($event->broadcastWith())->toBe($asWorker, $class.' builds its payload with the account of the request');
        }
    }
});

/**
 * The address of a route of the API in the community of the test
 *
 * @param  array<string, string>  $params
 */
function liveUpdateApi(string $route, array $params = []): string
{
    return ApiRoutes::path($route, [ApiRoutes::PARAM_COMMUNITY => test()->c['community']->cuid, ...$params]);
}

test('a new resource of a space for some access groups goes out with its identifiers only', function () {
    Queue::fake();
    Http::preventStrayRequests();
    Event::fake([CommunityResourceCreated::class]);
    $group = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Paid members']);
    $space = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Resources, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $space->accessGroups()->attach($group);

    $this->actingAs($this->c['owner'])->postJson(liveUpdateApi(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [ApiRoutes::PARAM_SPACE => $space->cuid]), [
        'name' => 'The reading list',
        'description' => 'For the paid members',
        'url' => 'https://example.com/an-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ])->assertCreated();

    $event = Event::dispatched(CommunityResourceCreated::class)->sole()[0];
    app('auth')->forgetGuards();
    $payload = $event->broadcastWith();
    expect(array_keys($payload))->toBe(['community_cuid', 'space_cuid', 'resource_cuid'])
        ->and($payload['space_cuid'])->toBe($space->cuid)
        ->and((string) json_encode($payload))->not->toContain('reading list')->not->toContain('an-article');
});

test('the live update of a delete does not name the person who deleted', function () {
    $chat = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Chat]);
    $message = $this->actingAs($this->c['member'])->postJson(liveUpdateApi(ApiRoutes::COMMUNITY_SPACE_MESSAGES, [ApiRoutes::PARAM_SPACE => $chat->cuid]), ['body' => 'A message of the member'])->assertCreated()->json('data.cuid');

    Event::fake([SpaceContentChanged::class]);
    $this->actingAs($this->c['moderator'])->deleteJson(liveUpdateApi(ApiRoutes::COMMUNITY_SPACE_MESSAGE, [ApiRoutes::PARAM_SPACE => $chat->cuid, ApiRoutes::PARAM_MESSAGE => $message]))->assertSuccessful();

    $event = Event::dispatched(SpaceContentChanged::class)->map(fn (array $arguments) => $arguments[0])->sole(fn (SpaceContentChanged $event) => $event->name === SpaceContentChanged::MESSAGE_DELETED);
    expect($event->broadcastWith())->toBe(['space_cuid' => $chat->cuid, 'message_cuid' => $message, 'parent_cuid' => null])
        ->and((string) json_encode($event->broadcastWith()))->not->toContain($this->c['moderator']->cuid);
});

test('a live update does not come back to the page that made the change: the socket of that page is left out', function () {
    $discussion = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Discussion]);
    $post = fn (array $headers) => $this->flushHeaders()->actingAs($this->c['member'])->withHeaders($headers)
        ->postJson(liveUpdateApi(ApiRoutes::COMMUNITY_SPACE_POSTS, [ApiRoutes::PARAM_SPACE => $discussion->cuid]), ['title' => 'A title', 'body' => 'A body'])
        ->assertCreated();

    Event::fake([SpaceContentChanged::class]);
    $post(['X-Socket-ID' => '1234.5678']);
    // A request without a live connection, and a value that is not the id of a connection, leave nobody out
    $post([]);
    $post(['X-Socket-ID' => 'not-a-socket']);

    expect(Event::dispatched(SpaceContentChanged::class)->map(fn (array $arguments) => $arguments[0]->socket)->all())->toBe(['1234.5678', null, null]);
});
