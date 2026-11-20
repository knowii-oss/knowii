<?php

use App\ApiRoutes;
use App\Broadcasting\CommunityOnlineChannel;
use App\Broadcasting\ConversationPresenceChannel;
use App\Broadcasting\SpacePresenceChannel;
use App\Contracts\Chat\CreatesChatMessages;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Realtime\ReadsPresenceChannels;
use App\Contracts\Users\BlocksMembers;
use App\Contracts\Users\ExportsUsers;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Events\CommunityResources\CommunityResourceCreated;
use App\Events\Conversations\ConversationChanged;
use App\Events\Notifications\NotificationsChanged;
use App\Events\Spaces\SpaceContentChanged;
use App\Mail\CommunityInvitation as CommunityInvitationMail;
use App\Mail\CommunityOwnershipTransferred;
use App\Models\AccessGroup;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\Page;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Visus\Cuid2\Cuid2;

/*
 * "Your email address is never shown" (#1566): the text of the account settings. Member A of a community, and a visitor on
 * the public pages, read everything that shows member B: the address of B is nowhere in the responses, and no `email` key
 * of another person exists. B gets the own address from the own account endpoint.
 */

// An address that no page holds by accident: only the account and the profile of B
const EAP_ADDRESS_OF_B = 'eap-probe-b-q7x2k9w4@example.com';
const EAP_LOCAL_PART_OF_B = 'eap-probe-b-q7x2k9w4';

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    // Member A reads, member B is read
    $this->a = $this->c['member'];
    $this->b = User::factory()->withUserProfile()->create(['email' => EAP_ADDRESS_OF_B, 'name' => 'Bea Probe', 'username' => 'bea-probe']);
    $this->community->users()->attach($this->b, ['role' => 'member']);
    // B shows every field of the profile: the email address is never one of them
    $this->b->profile->forceFill(['bio' => 'The bio of B', 'location' => 'Brussels', 'phone' => '+32 000 00 00', 'field_visibility' => ['bio' => true, 'location' => true, 'phone' => true]])->save();

    $group = SpaceGroup::factory()->create(['community_id' => $this->community->id]);
    $make = fn (KnowiiSpaceType $type, KnowiiSpaceVisibility $visibility) => Space::factory()->inGroup($group)->create(['type' => $type, 'visibility' => $visibility]);
    $this->discussion = $make(KnowiiSpaceType::Discussion, KnowiiSpaceVisibility::Public);
    $this->chat = $make(KnowiiSpaceType::Chat, KnowiiSpaceVisibility::Members);
    $this->pagesSpace = $make(KnowiiSpaceType::Pages, KnowiiSpaceVisibility::Public);
    $this->resourcesSpace = $make(KnowiiSpaceType::Resources, KnowiiSpaceVisibility::Public);

    // A post of B and its comments; a post of A that B comments, with a mention of A (two notifications that B causes)
    $this->postOfB = app(CreatesPosts::class)->create($this->b, $this->discussion, ['title' => 'A post of B', 'body' => 'The text of B']);
    $this->commentOfB = app(CreatesComments::class)->create($this->b, $this->postOfB, ['body' => 'A comment of B']);
    $this->postOfA = app(CreatesPosts::class)->create($this->a, $this->discussion, ['title' => 'A post of A', 'body' => 'The text of A']);
    app(CreatesComments::class)->create($this->b, $this->postOfA, ['body' => 'Hello @'.$this->a->username]);

    // A chat message of B with a reply of B in its thread, and a mention of A
    $this->messageOfB = app(CreatesChatMessages::class)->create($this->b, $this->chat, ['body' => 'Hi @'.$this->a->username]);
    app(CreatesChatMessages::class)->create($this->b, $this->chat, ['body' => 'A reply of B', 'parent_cuid' => $this->messageOfB->cuid]);

    // A page and a resource of B, with a comment of B on the resource
    $this->pageOfB = Page::factory()->inSpace($this->pagesSpace)->create(['author_id' => $this->b->id, 'title' => 'A page of B', 'position' => 1]);
    $this->resourceOfB = CommunityResource::factory()->create(['space_id' => $this->resourcesSpace->id, 'curator_id' => $this->b->profile->id]);
    $this->resourceCommentOfB = Comment::create([
        'cuid' => (new Cuid2)->toString(), 'community_id' => $this->community->id, 'community_resource_id' => $this->resourceOfB->id,
        'author_id' => $this->b->id, 'body' => 'A comment of B on the resource', 'body_html' => '<p>A comment of B on the resource</p>',
    ]);

    // A conversation that B starts with A
    $this->conversationCuid = $this->actingAs($this->b)->postJson(
        ApiRoutes::path(ApiRoutes::COMMUNITY_CONVERSATIONS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]),
        ['participant_cuids' => [$this->a->cuid], 'body' => 'A direct message of B'],
    )->assertCreated()->json('data.cuid');
    $this->directMessageOfB = ChatMessage::query()->whereHas('conversation', fn ($query) => $query->where('cuid', $this->conversationCuid))->firstOrFail();
    app('auth')->forgetGuards();

    // The reactions of B
    foreach ([$this->postOfA, $this->postOfB, $this->commentOfB, $this->resourceCommentOfB, $this->messageOfB, $this->directMessageOfB] as $item) {
        $item->reactions()->create(['community_id' => $this->community->id, 'user_id' => $this->b->id, 'emoji' => '👍']);
    }

    // B is online, in the community and in the chat space
    eapFakePresence([$this->b->cuid]);
});

/**
 * @param  list<string>  $cuids
 */
function eapFakePresence(array $cuids): void
{
    app()->instance(ReadsPresenceChannels::class, new class($cuids) implements ReadsPresenceChannels
    {
        /** @param list<string> $cuids */
        public function __construct(private array $cuids) {}

        public function memberCuids(string $channel): array
        {
            return $this->cuids;
        }
    });
}

/**
 * The values of every `email` key, at any depth
 *
 * @param  array<array-key, mixed>  $data
 * @return list<mixed>
 */
function eapEmailValues(array $data): array
{
    $values = [];
    foreach ($data as $key => $value) {
        if ($key === 'email') {
            $values[] = $value;
        }
        if (is_array($value)) {
            $values = [...$values, ...eapEmailValues($value)];
        }
    }

    return $values;
}

/**
 * The address of B, and of any other person, is nowhere in the text. The only `email` key is the one of the reader (the
 * shared props of a page hold the own account); a visitor gets none.
 *
 * @param  array<array-key, mixed>  $data
 */
function eapAssertNoAddressOfOthersIn(string $text, array $data, ?User $reader, string $where): void
{
    expect($text)->not->toContain(EAP_ADDRESS_OF_B, $where)
        ->and($text)->not->toContain(EAP_LOCAL_PART_OF_B, $where);

    foreach (User::query()->get() as $person) {
        if ($reader !== null && $person->is($reader)) {
            continue;
        }
        expect($text)->not->toContain($person->email, $where);
    }

    foreach (eapEmailValues($data) as $value) {
        expect($reader)->not->toBeNull($where.': a visitor gets an email key')
            ->and($value)->toBe($reader?->email, $where.': an email key of another person');
    }
}

function eapAssertNoAddressOfOthers(TestResponse $response, ?User $reader, string $where): void
{
    $response->assertOk();
    $text = (string) $response->getContent();
    // A page holds its props in the HTML; an API response is JSON
    $page = $response->baseResponse->headers->get('Content-Type') === 'application/json' ? null : $response->viewData('page');
    $data = is_array($page) ? $page : $response->json();

    eapAssertNoAddressOfOthersIn($text, is_array($data) ? $data : [], $reader, $where);
}

/**
 * @param  array<string, string>  $parameters
 */
function eapApi(string $route, array $parameters = []): string
{
    $test = test();

    return ApiRoutes::path($route, [
        ApiRoutes::PARAM_COMMUNITY => $test->community->cuid,
        ...$parameters,
    ]);
}

/**
 * @return array<string, string> The API reads of a member that show B
 */
function eapMemberApiReads(): array
{
    $test = test();
    $discussion = [ApiRoutes::PARAM_SPACE => $test->discussion->cuid];
    $post = [...$discussion, ApiRoutes::PARAM_POST => $test->postOfB->cuid];
    $chat = [ApiRoutes::PARAM_SPACE => $test->chat->cuid];
    $message = [...$chat, ApiRoutes::PARAM_MESSAGE => $test->messageOfB->cuid];
    $conversation = [ApiRoutes::PARAM_CONVERSATION => $test->conversationCuid];
    $resource = [ApiRoutes::PARAM_SPACE => $test->resourcesSpace->cuid, ApiRoutes::PARAM_RESOURCE => $test->resourceOfB->cuid];

    return [
        'member list' => eapApi(ApiRoutes::COMMUNITY_MEMBERS),
        'mentions lookup' => eapApi(ApiRoutes::COMMUNITY_MEMBERS).'?query=bea',
        'posts of a space' => eapApi(ApiRoutes::COMMUNITY_SPACE_POSTS, $discussion),
        'post of B' => eapApi(ApiRoutes::COMMUNITY_SPACE_POST, $post),
        'comments of the post of B' => eapApi(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, $post),
        'reactions to a post' => eapApi(ApiRoutes::COMMUNITY_SPACE_POST_REACTIONS, [...$discussion, ApiRoutes::PARAM_POST => $test->postOfA->cuid]),
        'reactions to a comment' => eapApi(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT_REACTIONS, [...$post, ApiRoutes::PARAM_COMMENT => $test->commentOfB->cuid]),
        'newest posts' => eapApi(ApiRoutes::COMMUNITY_NEWEST_POSTS),
        'chat messages' => eapApi(ApiRoutes::COMMUNITY_SPACE_MESSAGES, $chat),
        'chat message of B' => eapApi(ApiRoutes::COMMUNITY_SPACE_MESSAGE, $message),
        'thread of a chat message' => eapApi(ApiRoutes::COMMUNITY_SPACE_MESSAGE_THREAD, $message),
        'reactions to a chat message' => eapApi(ApiRoutes::COMMUNITY_SPACE_MESSAGE_REACTIONS, $message),
        'conversations' => eapApi(ApiRoutes::COMMUNITY_CONVERSATIONS),
        'conversation with B' => eapApi(ApiRoutes::COMMUNITY_CONVERSATION, $conversation),
        'direct messages' => eapApi(ApiRoutes::COMMUNITY_CONVERSATION_MESSAGES, $conversation),
        'direct message of B' => eapApi(ApiRoutes::COMMUNITY_CONVERSATION_MESSAGE, [...$conversation, ApiRoutes::PARAM_MESSAGE => $test->directMessageOfB->cuid]),
        'reactions to a direct message' => eapApi(ApiRoutes::COMMUNITY_CONVERSATION_MESSAGE_REACTIONS, [...$conversation, ApiRoutes::PARAM_MESSAGE => $test->directMessageOfB->cuid]),
        'pages' => eapApi(ApiRoutes::COMMUNITY_SPACE_PAGES, [ApiRoutes::PARAM_SPACE => $test->pagesSpace->cuid]),
        'page of B' => eapApi(ApiRoutes::COMMUNITY_SPACE_PAGE, [ApiRoutes::PARAM_SPACE => $test->pagesSpace->cuid, ApiRoutes::PARAM_PAGE => $test->pageOfB->cuid]),
        'resources' => eapApi(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [ApiRoutes::PARAM_SPACE => $test->resourcesSpace->cuid]),
        'resource of B' => eapApi(ApiRoutes::COMMUNITY_SPACE_RESOURCE, $resource),
        'comments of the resource' => eapApi(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENTS, $resource),
        'reactions to a resource comment' => eapApi(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT_REACTIONS, [...$resource, ApiRoutes::PARAM_RESOURCE_COMMENT => $test->resourceCommentOfB->cuid]),
        'notifications' => ApiRoutes::path(ApiRoutes::NOTIFICATIONS),
        'catch up' => eapApi(ApiRoutes::COMMUNITY_CATCH_UP).'?since='.now('UTC')->toDateString(),
        'online members' => eapApi(ApiRoutes::COMMUNITY_ONLINE_MEMBERS),
        'members in a chat space' => eapApi(ApiRoutes::COMMUNITY_SPACE_ONLINE_MEMBERS, $chat),
        'own account of A' => ApiRoutes::path(ApiRoutes::USER),
    ];
}

/**
 * @return array<string, string> The web pages of a member that show B
 */
function eapMemberPages(): array
{
    $test = test();
    $slug = $test->community->slug;

    return [
        'home of the community' => route('communities.show', ['communitySlug' => $slug]),
        'about page' => route('communities.about', ['communitySlug' => $slug]),
        // The member directory (#148), with each of its two sorts and with a search that finds B
        'member directory' => route('members.index', ['communitySlug' => $slug]),
        'member directory, newest first' => route('members.index', ['communitySlug' => $slug, 'sort' => 'newest']),
        'member directory, search of B' => route('members.index', ['communitySlug' => $slug, 'query' => 'bea']),
        'profile of B' => route('members.show', ['communitySlug' => $slug, 'username' => $test->b->username]),
        'discussion space' => route('spaces.show', ['communitySlug' => $slug, 'spaceSlug' => $test->discussion->slug]),
        'post of B' => route('posts.show', ['communitySlug' => $slug, 'spaceSlug' => $test->discussion->slug, 'postSlug' => $test->postOfB->slug]),
        'chat space' => route('spaces.show', ['communitySlug' => $slug, 'spaceSlug' => $test->chat->slug]),
        'pages space' => route('spaces.show', ['communitySlug' => $slug, 'spaceSlug' => $test->pagesSpace->slug]),
        'page of B' => route('pages.show', ['communitySlug' => $slug, 'spaceSlug' => $test->pagesSpace->slug, 'pageSlug' => $test->pageOfB->slug]),
        'resources space' => route('spaces.show', ['communitySlug' => $slug, 'spaceSlug' => $test->resourcesSpace->slug]),
        'resource of B' => route('resources.show', ['communitySlug' => $slug, 'spaceSlug' => $test->resourcesSpace->slug, 'resourceSlug' => $test->resourceOfB->slug]),
        'messages' => route('messages.index', ['communitySlug' => $slug]),
        'conversation with B' => route('messages.show', ['communitySlug' => $slug, 'conversationCuid' => $test->conversationCuid]),
        'notifications page' => route('notifications.index'),
        'dashboard' => route('dashboard'),
    ];
}

test('the fixture is what the tests expect: B caused notifications of A, and B is online', function () {
    $this->actingAs($this->a)->getJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS))
        ->assertOk()
        ->assertJsonPath('data.0.actor.cuid', $this->b->cuid);
    $this->actingAs($this->a)->getJson(eapApi(ApiRoutes::COMMUNITY_ONLINE_MEMBERS))
        ->assertOk()
        ->assertJsonPath('data.0.cuid', $this->b->cuid);
    $this->actingAs($this->a)->getJson(eapApi(ApiRoutes::COMMUNITY_MEMBERS).'?query=bea')
        ->assertOk()
        ->assertJsonPath('data.0.cuid', $this->b->cuid);
});

test('B gets the own address from the own account endpoint', function () {
    $this->actingAs($this->b)->getJson(ApiRoutes::path(ApiRoutes::USER))
        ->assertOk()
        ->assertJsonPath('data.email', EAP_ADDRESS_OF_B)
        ->assertJsonPath('data.cuid', $this->b->cuid);
});

test('member A reads B through the API, and gets no email address of B or of anyone else', function () {
    foreach (eapMemberApiReads() as $where => $url) {
        eapAssertNoAddressOfOthers($this->actingAs($this->a)->getJson($url), $this->a, $where);
    }
});

test('member A opens the pages that show B, and gets no email address of B or of anyone else', function () {
    foreach (eapMemberPages() as $where => $url) {
        eapAssertNoAddressOfOthers($this->actingAs($this->a)->get($url), $this->a, $where);
    }

    // The scan reads the props: the page of B holds what B shows, and the own account of A
    $text = (string) $this->actingAs($this->a)->get(eapMemberPages()['profile of B'])->getContent();
    expect($text)->toContain('The bio of B')
        ->and($text)->toContain('+32 000 00 00')
        ->and($text)->toContain($this->a->email);
});

test('a visitor reads the public page of the community and a public space, and gets no email address at all', function () {
    $slug = $this->community->slug;
    $discussion = [ApiRoutes::PARAM_SPACE => $this->discussion->cuid];
    $post = [...$discussion, ApiRoutes::PARAM_POST => $this->postOfB->cuid];
    $resource = [ApiRoutes::PARAM_SPACE => $this->resourcesSpace->cuid, ApiRoutes::PARAM_RESOURCE => $this->resourceOfB->cuid];

    $reads = [
        'public page of the community' => route('communities.show', ['communitySlug' => $slug]),
        'public discussion space' => route('spaces.show', ['communitySlug' => $slug, 'spaceSlug' => $this->discussion->slug]),
        'public post of B' => route('posts.show', ['communitySlug' => $slug, 'spaceSlug' => $this->discussion->slug, 'postSlug' => $this->postOfB->slug]),
        'public page of B' => route('pages.show', ['communitySlug' => $slug, 'spaceSlug' => $this->pagesSpace->slug, 'pageSlug' => $this->pageOfB->slug]),
        'public resource of B' => route('resources.show', ['communitySlug' => $slug, 'spaceSlug' => $this->resourcesSpace->slug, 'resourceSlug' => $this->resourceOfB->slug]),
        'community' => eapApi(ApiRoutes::COMMUNITY),
        'space groups' => eapApi(ApiRoutes::COMMUNITY_SPACE_GROUPS),
        'newest posts' => eapApi(ApiRoutes::COMMUNITY_NEWEST_POSTS),
        'posts of a public space' => eapApi(ApiRoutes::COMMUNITY_SPACE_POSTS, $discussion),
        'public post of B (API)' => eapApi(ApiRoutes::COMMUNITY_SPACE_POST, $post),
        'comments of the public post of B' => eapApi(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, $post),
        'pages of a public space' => eapApi(ApiRoutes::COMMUNITY_SPACE_PAGES, [ApiRoutes::PARAM_SPACE => $this->pagesSpace->cuid]),
        'resources of a public space' => eapApi(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [ApiRoutes::PARAM_SPACE => $this->resourcesSpace->cuid]),
        'public resource of B (API)' => eapApi(ApiRoutes::COMMUNITY_SPACE_RESOURCE, $resource),
        'comments of the public resource' => eapApi(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENTS, $resource),
    ];

    foreach ($reads as $where => $url) {
        app('auth')->forgetGuards();
        $response = str_starts_with($url, ApiRoutes::BASE_PATH) ? $this->getJson($url) : $this->get($url);
        eapAssertNoAddressOfOthers($response, null, $where);
    }

    // The public page is the page of a visitor, not the home of a member
    app('auth')->forgetGuards();
    $this->get(route('communities.show', ['communitySlug' => $slug]))->assertInertia(fn ($page) => $page->component('Communities/Public'));
});

test('the people who manage the community get no email address of a member in the member list, the groups and the settings', function (string $manager) {
    // A moderator manages the members only with this right (#1240)
    $this->community->update(['moderator_permissions' => [...$this->community->moderatorPermissions(), 'manage_members' => true]]);
    $reader = $this->c[$manager];
    $group = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Buyers']);
    $group->members()->attach($this->b);

    $reads = [
        'member list' => eapApi(ApiRoutes::COMMUNITY_MEMBERS),
        'removed members' => eapApi(ApiRoutes::COMMUNITY_JOIN_BLOCKS),
        'members of an access group' => eapApi(ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBERS, [ApiRoutes::PARAM_ACCESS_GROUP => $group->cuid]),
        'invitations' => eapApi(ApiRoutes::COMMUNITY_INVITATIONS),
        'settings page' => route('communities.settings', ['communitySlug' => $this->community->slug]),
    ];

    foreach ($reads as $where => $url) {
        $response = str_starts_with($url, ApiRoutes::BASE_PATH) ? $this->actingAs($reader)->getJson($url) : $this->actingAs($reader)->get($url);
        eapAssertNoAddressOfOthers($response, $reader, $where);
    }
})->with(['owner', 'admin', 'moderator']);

test('what the WebSocket server gives the other members of B holds no address: presence and events', function () {
    // The presence channels: what the other members get when B joins
    $joins = [
        'community presence' => app(CommunityOnlineChannel::class)->join($this->b, $this->community->cuid),
        'space presence' => app(SpacePresenceChannel::class)->join($this->b, $this->community->cuid, $this->chat->cuid),
        'conversation presence' => app(ConversationPresenceChannel::class)->join($this->b, $this->conversationCuid),
    ];
    foreach ($joins as $where => $info) {
        expect($info)->toBeArray($where)->and($info['cuid'] ?? null)->toBe($this->b->cuid, $where);
        eapAssertNoAddressOfOthersIn((string) json_encode($info), is_array($info) ? $info : [], null, $where);
    }

    // The events of what B does
    $broadcasts = [SpaceContentChanged::class, ConversationChanged::class, NotificationsChanged::class, CommunityMembersChanged::class];
    Event::fake($broadcasts);
    $post = app(CreatesPosts::class)->create($this->b, $this->discussion, ['title' => 'Another post of B', 'body' => 'More text']);
    app(CreatesComments::class)->create($this->b, $this->postOfA, ['body' => 'Again @'.$this->a->username]);
    app(CreatesChatMessages::class)->create($this->b, $this->chat, ['body' => 'Another message of B']);
    $this->actingAs($this->b)->postJson(eapApi(ApiRoutes::COMMUNITY_SPACE_POST_REACTIONS, [ApiRoutes::PARAM_SPACE => $this->discussion->cuid, ApiRoutes::PARAM_POST => $post->cuid]), ['emoji' => '🎉'])->assertOk();
    $this->actingAs($this->b)->postJson(eapApi(ApiRoutes::COMMUNITY_CONVERSATION_MESSAGES, [ApiRoutes::PARAM_CONVERSATION => $this->conversationCuid]), ['body' => 'Another direct message'])->assertCreated();

    $count = 0;
    foreach ($broadcasts as $class) {
        foreach (Event::dispatched($class) as $arguments) {
            $event = $arguments[0];
            $payload = method_exists($event, 'broadcastWith') ? $event->broadcastWith() : get_object_vars($event);
            eapAssertNoAddressOfOthersIn((string) json_encode($payload), $payload, null, $class);
            $count++;
        }
    }
    expect($count)->toBeGreaterThan(0);

    // The event of a resource holds its curator
    $payload = (new CommunityResourceCreated($this->resourceOfB))->broadcastWith();
    $json = (string) json_encode($payload);
    expect($json)->toContain('Bea Probe');
    eapAssertNoAddressOfOthersIn($json, (array) json_decode($json, true), null, 'resource event');
});

test('the emails that name B to another person do not hold the address of B', function () {
    $invitation = $this->community->communityInvitations()->create(['email' => 'eap-invitee@example.com', 'role' => 'member']);
    $ownCommunity = Community::factory()->create(['owner_id' => $this->b->id]);

    $mails = [
        'invitation sent by B' => new CommunityInvitationMail($invitation, $this->b),
        'ownership given by B' => new CommunityOwnershipTransferred($ownCommunity, $this->b),
    ];
    foreach ($mails as $where => $mail) {
        $html = $mail->render();
        expect($html)->toContain('Bea Probe')
            ->and($html)->not->toContain(EAP_ADDRESS_OF_B, $where)
            ->and($html)->not->toContain(EAP_LOCAL_PART_OF_B, $where)
            ->and($mail->hasFrom(EAP_ADDRESS_OF_B))->toBeFalse($where)
            ->and($mail->hasReplyTo(EAP_ADDRESS_OF_B))->toBeFalse($where)
            ->and($mail->hasCc(EAP_ADDRESS_OF_B))->toBeFalse($where)
            ->and($mail->hasBcc(EAP_ADDRESS_OF_B))->toBeFalse($where);
    }
});

test('the export of the data of A holds no address of B, also after A blocks B', function () {
    $read = function (): string {
        $zip = new ZipArchive;
        expect($zip->open(app(ExportsUsers::class)->export($this->a)))->toBeTrue();
        $text = '';
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $text .= (string) $zip->getFromIndex($index);
        }
        $zip->close();

        return $text;
    };

    $text = $read();
    expect($text)->toContain($this->a->email)
        ->and($text)->not->toContain(EAP_ADDRESS_OF_B)
        ->and($text)->not->toContain(EAP_LOCAL_PART_OF_B);

    app(BlocksMembers::class)->block($this->a, $this->b);
    $text = $read();
    expect($text)->toContain($this->b->username)
        ->and($text)->not->toContain(EAP_ADDRESS_OF_B)
        ->and($text)->not->toContain(EAP_LOCAL_PART_OF_B);
});
