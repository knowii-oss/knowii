<?php

use App\ApiRoutes;
use App\Constants;
use App\Contracts\AccessGroups\AddsAccessGroupMembers;
use App\Contracts\AccessGroups\DeletesAccessGroups;
use App\Contracts\AccessGroups\RemovesAccessGroupMembers;
use App\Contracts\AccessGroups\SetsAccessGroupSpaceGroups;
use App\Contracts\Communities\UpdatesCommunities;
use App\Contracts\Communities\UpdatesModeratorPermissions;
use App\Contracts\CommunityMembers\LeavesCommunities;
use App\Contracts\CommunityMembers\RemovesCommunityMembers;
use App\Contracts\CommunityMembers\UpdatesCommunityMemberRoles;
use App\Contracts\Entitlements\SyncsEntitlementRules;
use App\Contracts\Realtime\EndsLiveConnections;
use App\Contracts\SpaceGroups\SetsSpaceGroupAccessGroups;
use App\Contracts\Spaces\UpdatesSpaces;
use App\Contracts\Users\BlocksMembers;
use App\Contracts\Users\UpdatesOnlineStatus;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceContentChanged;
use App\Jobs\EndLiveConnections;
use App\Models\AccessGroup;
use App\Models\AccessGroupRule;
use App\Models\Conversation;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Support\LiveConnections;
use App\Support\ReverbLiveConnections;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Reverb\Application;
use Laravel\Reverb\ConfigApplicationProvider;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Contracts\WebSocketConnection;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\EventDispatcher;
use Laravel\Reverb\Protocols\Pusher\EventHandler;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelManager;

/*
 * When the access of an account ends or shrinks, its live connections end, and the page connects again with the rights of
 * now (ADR 0075). Each change calls the contract that ends the connections (a fake here) for the accounts that lost
 * something, and for nobody else, after the commit, through a job of the queue `broadcasts`.
 */

/**
 * A fake of the contract: the accounts whose connections end, in the order of the calls
 */
function liveEnded(): ArrayObject
{
    $ended = new ArrayObject;
    app()->instance(EndsLiveConnections::class, new class($ended) implements EndsLiveConnections
    {
        public function __construct(private readonly ArrayObject $ended) {}

        public function endFor(string $userCuid): void
        {
            $this->ended[] = $userCuid;
        }
    });

    return $ended;
}

/**
 * The cuids of some users, sorted: a change of many members ends them in no set order
 *
 * @return list<string>
 */
function liveCuids(User ...$users): array
{
    $cuids = array_map(static fn (User $user): string => $user->cuid, $users);
    sort($cuids);

    return $cuids;
}

/**
 * @return list<string>
 */
function liveSorted(ArrayObject $ended): array
{
    $cuids = $ended->getArrayCopy();
    sort($cuids);

    return $cuids;
}

/**
 * Every member of the community of the test and its owner
 *
 * @return list<string>
 */
function liveEveryone(): array
{
    $c = test()->c;

    return liveCuids($c['owner'], $c['admin'], $c['moderator'], $c['member']);
}

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $this->spaceGroup = SpaceGroup::factory()->create(['community_id' => $this->community->id]);
    $this->group = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Paid members']);
    $this->space = Space::factory()->inGroup($this->spaceGroup)->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $this->space->accessGroups()->attach($this->group);
});

// ---------------------------------------------------------------------------------------------------------------------
// Every connection is known by its account
// ---------------------------------------------------------------------------------------------------------------------

/**
 * The answer of the authorization of a channel, with a broadcaster that checks the channels
 */
function liveChannelAnswer(User $user, string $channel): TestResponse
{
    return withCheckingBroadcaster(function () use ($user, $channel): TestResponse {
        newBrowser();

        return test()->actingAs($user)->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel]);
    });
}

test('the page of an account joins the presence channel of that account alone: its member data is the cuid only', function () {
    $member = $this->c['member'];
    // Also a member who hides the online status: the channel tells nobody else anything
    $member->forceFill(['show_online_status' => false])->save();

    $answer = liveChannelAnswer($member, 'presence-user.'.$member->cuid)->assertOk();

    expect($answer->json('auth'))->toStartWith('test-key:')
        ->and(json_decode((string) $answer->json('channel_data'), true))->toBe(['user_id' => $member->cuid, 'user_info' => ['cuid' => $member->cuid]]);
    liveChannelAnswer($member, 'presence-user.'.$this->c['admin']->cuid)->assertForbidden()->assertJsonMissingPath('auth');
});

test('the authorization of every private channel is signed with the account: the WebSocket server keeps it on the subscription', function () {
    $member = $this->c['member'];

    foreach (['private-user.'.$member->cuid, 'private-community.'.$this->community->cuid] as $channel) {
        $answer = liveChannelAnswer($member, $channel)->assertOk();
        expect($answer->json('channel_data'))->toBe(json_encode(['user_id' => $member->cuid]))
            ->and($answer->json('auth'))->toBe('test-key:'.hash_hmac('sha256', '1234.5678:'.$channel.':'.$answer->json('channel_data'), 'test-secret'));
    }
});

/**
 * Reverb in the process of the test: its channel manager, the subscription with the answer of
 * `POST /broadcasting/auth`, the dispatch of an event, and the end of the connections of an account (the loop of its HTTP
 * API `terminate_connections`). The contract that ends the connections reaches that loop
 */
function liveInProcessReverb(): Application
{
    app()->singleton(ChannelManager::class, fn () => new ArrayChannelManager);
    app()->bind(ChannelConnectionManager::class, fn () => new ArrayChannelConnectionManager);
    $reverb = new Application('test-app', 'test-key', 'test-secret', 60, 30, ['*'], 10_000);

    app()->instance(EndsLiveConnections::class, new class($reverb) implements EndsLiveConnections
    {
        public function __construct(private readonly Application $reverb) {}

        public function endFor(string $userCuid): void
        {
            collect(app(ChannelManager::class)->for($this->reverb)->connections())->each(function (ChannelConnection $connection) use ($userCuid): void {
                if ((string) $connection->data('user_id') === $userCuid) {
                    $connection->disconnect();
                }
            });
        }
    });

    return $reverb;
}

/**
 * A live connection on the Reverb of the test: it keeps the messages that it gets, and whether it ended
 */
function liveSocket(string $socketId, Application $reverb): Connection
{
    return new class($socketId, $reverb) extends Connection
    {
        /** @var list<string> */
        public array $sent = [];

        public bool $ended = false;

        public function __construct(private readonly string $socketId, Application $reverb)
        {
            parent::__construct(Mockery::mock(WebSocketConnection::class), $reverb, null);
        }

        public function identifier(): string
        {
            return $this->socketId;
        }

        public function id(): string
        {
            return $this->socketId;
        }

        public function send(string $message): void
        {
            $this->sent[] = $message;
        }

        public function control(string $type = 'ping'): void {}

        public function terminate(): void
        {
            $this->ended = true;
            app(ChannelManager::class)->for($this->app())->unsubscribeFromAll($this);
        }
    };
}

/**
 * The connection subscribes to a channel with the answer of `POST /broadcasting/auth` for the account
 */
function liveSubscribe(Connection $socket, User $user, string $channel): void
{
    $answer = withCheckingBroadcaster(function () use ($user, $socket, $channel): TestResponse {
        newBrowser();

        return test()->actingAs($user)->postJson('/broadcasting/auth', ['socket_id' => $socket->id(), 'channel_name' => $channel]);
    })->assertOk()->json();

    app(EventHandler::class)->subscribe($socket, $channel, $answer['auth'], $answer['channel_data'] ?? null);
}

test('a connection that holds private channels only ends with the access of its account, and gets no event after', function () {
    $reverb = liveInProcessReverb();
    $member = $this->c['member'];
    $space = Space::factory()->inGroup($this->spaceGroup)->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Members]);
    $spaceChannel = 'private-community.'.$this->community->cuid.'.space.'.$space->cuid;

    // A page that joins the presence channel of the account, and a connection that holds private channels only
    $page = liveSocket('111.111', $reverb);
    liveSubscribe($page, $member, 'presence-user.'.$member->cuid);
    liveSubscribe($page, $member, $spaceChannel);
    $other = liveSocket('222.222', $reverb);
    liveSubscribe($other, $member, $spaceChannel);
    liveSubscribe($other, $member, 'private-community.'.$this->community->cuid);

    app(RemovesCommunityMembers::class)->remove($this->c['owner'], $this->community, $member->cuid);

    expect($page->ended)->toBeTrue()
        ->and($other->ended)->toBeTrue();

    $event = new SpaceContentChanged(SpaceContentChanged::POST_CREATED, $this->community->cuid, $space->cuid, ['post_cuid' => 'post-after-removal']);
    EventDispatcher::dispatch($reverb, ['event' => $event->broadcastAs(), 'channel' => $spaceChannel, 'data' => json_encode($event->broadcastWith())]);
    expect(collect([...$page->sent, ...$other->sent])->contains(fn (string $message): bool => str_contains($message, 'post-after-removal')))->toBeFalse();
});

test('a connection ends with its account when it holds the channel of the account only, or when it left the presence channel of the account', function () {
    $reverb = liveInProcessReverb();
    $member = $this->c['member'];

    $userOnly = liveSocket('333.333', $reverb);
    liveSubscribe($userOnly, $member, 'private-user.'.$member->cuid);
    $left = liveSocket('444.444', $reverb);
    liveSubscribe($left, $member, 'presence-user.'.$member->cuid);
    liveSubscribe($left, $member, 'private-community.'.$this->community->cuid);
    app(EventHandler::class)->unsubscribe($left, 'presence-user.'.$member->cuid);
    // The connection of another account stays
    $admin = liveSocket('555.555', $reverb);
    liveSubscribe($admin, $this->c['admin'], 'private-community.'.$this->community->cuid);

    app(EndsLiveConnections::class)->endFor($member->cuid);

    expect($userOnly->ended)->toBeTrue()
        ->and($left->ended)->toBeTrue()
        ->and($admin->ended)->toBeFalse();
});

test('the account in the signature of a private channel is part of what the WebSocket server checks', function () {
    $reverb = liveInProcessReverb();
    $member = $this->c['member'];
    $channel = 'private-community.'.$this->community->cuid;
    $answer = liveChannelAnswer($member, $channel)->assertOk()->json();
    $socket = liveSocket('1234.5678', $reverb);

    expect(fn () => app(EventHandler::class)->subscribe($socket, $channel, $answer['auth'], null))->toThrow(ConnectionUnauthorized::class)
        ->and(fn () => app(EventHandler::class)->subscribe($socket, $channel, $answer['auth'], json_encode(['user_id' => $this->c['admin']->cuid])))->toThrow(ConnectionUnauthorized::class);

    app(EventHandler::class)->subscribe($socket, $channel, $answer['auth'], $answer['channel_data']);
    // A private channel tells nobody its subscribers
    expect(app(ChannelManager::class)->for($reverb)->find($channel)?->data())->toBe([]);
});

test('one connection belongs to one account: the authorization of a channel for another account on the same connection is refused', function () {
    $member = $this->c['member'];
    $admin = $this->c['admin'];
    $answer = fn (User $user, string $socket, string $channel): TestResponse => withCheckingBroadcaster(function () use ($user, $socket, $channel): TestResponse {
        newBrowser();

        return test()->actingAs($user)->postJson('/broadcasting/auth', ['socket_id' => $socket, 'channel_name' => $channel]);
    });

    $answer($admin, '555.555', 'private-user.'.$admin->cuid)->assertOk();
    $answer($member, '555.555', 'private-community.'.$this->community->cuid)->assertForbidden()->assertJsonMissingPath('auth');
    $answer($member, '555.555', 'presence-user.'.$member->cuid)->assertForbidden()->assertJsonMissingPath('auth');

    // The account of the connection signs more channels on it, and the other account signs on its own connection
    $answer($admin, '555.555', 'private-community.'.$this->community->cuid)->assertOk();
    $answer($member, '666.666', 'private-community.'.$this->community->cuid)->assertOk();

    // A refusal of a rule does not take the connection
    $answer($member, '777.777', 'private-user.'.$admin->cuid)->assertForbidden();
    $answer($admin, '777.777', 'private-user.'.$admin->cuid)->assertOk();

    expect(Cache::get('live-socket:555.555'))->toBe($admin->cuid);
});

// ---------------------------------------------------------------------------------------------------------------------
// The call: after the commit, in a job of the queue `broadcasts`
// ---------------------------------------------------------------------------------------------------------------------

test('the end of the connections is a job of the queue broadcasts, sent after the commit, and never when the change is undone', function () {
    $ended = liveEnded();

    DB::transaction(function () use ($ended) {
        LiveConnections::endFor($this->c['member'], $this->c['admin']->cuid, $this->c['member']);
        expect($ended->getArrayCopy())->toBe([]);
    });
    expect($ended->getArrayCopy())->toBe([$this->c['member']->cuid, $this->c['admin']->cuid]);

    try {
        DB::transaction(function () {
            LiveConnections::endFor($this->c['owner']);
            throw new RuntimeException('undone');
        });
    } catch (RuntimeException) {
        // The change is undone: its accounts keep their connections
    }
    expect($ended->getArrayCopy())->toBe([$this->c['member']->cuid, $this->c['admin']->cuid]);

    Queue::fake();
    LiveConnections::endFor($this->c['member']);
    Queue::assertPushedOn(Constants::QUEUE_BROADCASTS, EndLiveConnections::class, fn (EndLiveConnections $job) => $job->userCuids === [$this->c['member']->cuid]);
});

test('the job ends the connections of each account, again without harm, and a failure is logged with the cuids only and tried again', function () {
    $ended = liveEnded();
    (new EndLiveConnections(['cuid-a', 'cuid-b']))->handle(app(EndsLiveConnections::class));
    (new EndLiveConnections(['cuid-a', 'cuid-b']))->handle(app(EndsLiveConnections::class));
    expect($ended->getArrayCopy())->toBe(['cuid-a', 'cuid-b', 'cuid-a', 'cuid-b']);

    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb', 'key' => 'test-key', 'secret' => 'test-secret', 'app_id' => 'test-app',
            'options' => ['host' => 'reverb.example.test', 'port' => 443, 'scheme' => 'https', 'useTLS' => true],
        ],
    ]);
    app()->instance(EndsLiveConnections::class, new ReverbLiveConnections(['handler' => HandlerStack::create(new MockHandler([new Response(500, [], 'down')]))]));
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, fn (MessageLogged $event) => $logged[] = ['level' => $event->level, 'message' => $event->message, 'context' => $event->context]);

    $job = (new EndLiveConnections(['cuid-a']))->withFakeQueueInteractions();
    $job->handle(app(EndsLiveConnections::class));

    $job->assertReleased(EndLiveConnections::RETRY_SECONDS);
    expect($logged->getArrayCopy())->toHaveCount(1)
        ->and($logged[0]['level'])->toBe('warning')
        ->and($logged[0]['context'])->toBe(['users' => ['cuid-a'], 'error' => 'Pusher\ApiErrorException', 'attempt' => 1]);
});

// ---------------------------------------------------------------------------------------------------------------------
// Each change ends the connections of the accounts that lost something, and of nobody else
// ---------------------------------------------------------------------------------------------------------------------

test('a member who is removed, or who leaves', function () {
    $ended = liveEnded();

    app(RemovesCommunityMembers::class)->remove($this->c['owner'], $this->community, $this->c['member']->cuid);
    expect($ended->getArrayCopy())->toBe([$this->c['member']->cuid]);

    app(LeavesCommunities::class)->leave($this->c['moderator'], $this->community);
    expect($ended->getArrayCopy())->toBe([$this->c['member']->cuid, $this->c['moderator']->cuid]);
});

test('a role that goes down, and moderator rights that go off; never a change that gives more', function () {
    $ended = liveEnded();

    app(UpdatesCommunityMemberRoles::class)->update($this->c['owner'], $this->community, $this->c['member']->cuid, ['role' => 'moderator']);
    app(UpdatesModeratorPermissions::class)->update($this->c['owner'], $this->community, ['manage_members' => true]);
    expect($ended->getArrayCopy())->toBe([]);

    app(UpdatesCommunityMemberRoles::class)->update($this->c['owner'], $this->community, $this->c['admin']->cuid, ['role' => 'member']);
    expect($ended->getArrayCopy())->toBe([$this->c['admin']->cuid]);

    // The moderators now: the one of the test and the member who became one
    app(UpdatesModeratorPermissions::class)->update($this->c['owner'], $this->community, ['manage_members' => false]);
    expect(liveSorted($ended))->toBe(liveCuids($this->c['admin'], $this->c['moderator'], $this->c['member']));
});

test('a member who leaves an access group by hand; never one who joins it, nor one whom a rule keeps in it', function () {
    $ended = liveEnded();
    $owner = $this->c['owner'];

    app(AddsAccessGroupMembers::class)->add($owner, $this->group, ['member' => $this->c['member']->cuid]);
    expect($ended->getArrayCopy())->toBe([]);

    app(RemovesAccessGroupMembers::class)->remove($owner, $this->group, $this->c['member']->cuid);
    expect($ended->getArrayCopy())->toBe([$this->c['member']->cuid]);
});

test('a member whose access groups change by an entitlement rule', function () {
    entConfigure([$this->community->slug]);
    $ended = liveEnded();
    $member = $this->c['member'];
    entIdentity($member);
    AccessGroupRule::query()->create(['access_group_id' => $this->group->id, 'entitlement_key' => 'example.paid']);
    UserEntitlement::query()->create(['user_id' => $member->id, 'issuer' => ENT_ISSUER, 'key' => 'example.paid']);

    app(SyncsEntitlementRules::class)->syncMember($member->fresh());
    app(SyncsEntitlementRules::class)->syncMember($this->c['admin']->fresh());
    expect($ended->getArrayCopy())->toBe([$member->cuid]);

    UserEntitlement::query()->where('user_id', $member->id)->delete();
    app(SyncsEntitlementRules::class)->syncCommunity($this->community);
    expect($ended->getArrayCopy())->toBe([$member->cuid, $member->cuid]);
});

test('a change of many members ends the connections of every member of the community', function (string $change) {
    $owner = $this->c['owner'];
    // What the change needs, written before the fake: it ends nothing
    if ($change === 'an access group loses a space group') {
        $this->group->spaceGroups()->attach($this->spaceGroup);
    } elseif ($change === 'a space group loses an access group') {
        $this->spaceGroup->accessGroups()->attach($this->group);
    }
    $ended = liveEnded();

    match ($change) {
        'an access group is deleted' => app(DeletesAccessGroups::class)->delete($owner, $this->group),
        'an access group loses a space group' => app(SetsAccessGroupSpaceGroups::class)->set($owner, $this->group, ['space_groups' => []]),
        'a space group loses an access group' => app(SetsSpaceGroupAccessGroups::class)->set($owner, $this->spaceGroup, ['access_groups' => []]),
        'a space changes its access' => app(UpdatesSpaces::class)->update($owner, $this->space, ['visibility' => KnowiiSpaceVisibility::Members->value]),
        'a community changes its visibility' => app(UpdatesCommunities::class)->update($owner, $this->community, ['visibility' => KnowiiCommunityVisibility::Public->value]),
        'a community turns direct messages off' => app(UpdatesCommunities::class)->update($owner, $this->community, ['direct_messages_enabled' => false]),
    };

    expect(liveSorted($ended))->toBe(liveEveryone());
})->with([
    'an access group is deleted',
    'an access group loses a space group',
    'a space group loses an access group',
    'a space changes its access',
    'a community changes its visibility',
    'a community turns direct messages off',
]);

test('a change of a community or a space that keeps every access ends nothing', function () {
    $ended = liveEnded();

    app(UpdatesSpaces::class)->update($this->c['owner'], $this->space, ['name' => 'Another name']);
    app(UpdatesCommunities::class)->update($this->c['owner'], $this->community, ['name' => 'Another community name', 'direct_messages_enabled' => true]);
    app(SetsAccessGroupSpaceGroups::class)->set($this->c['owner'], $this->group, ['space_groups' => [$this->spaceGroup->cuid]]);

    expect($ended->getArrayCopy())->toBe([]);
});

test('the online status that goes hidden; never the one that is shown', function () {
    $ended = liveEnded();
    $member = $this->c['member'];

    app(UpdatesOnlineStatus::class)->update($member, ['show_online_status' => true]);
    expect($ended->getArrayCopy())->toBe([]);

    app(UpdatesOnlineStatus::class)->update($member, ['show_online_status' => false]);
    expect($ended->getArrayCopy())->toBe([$member->cuid]);
});

test('a block ends the connections of both members, and the presence of their conversation follows', function () {
    $member = $this->c['member'];
    $admin = $this->c['admin'];
    $conversation = Conversation::create(['community_id' => $this->community->id, 'created_by' => $member->id, 'direct_key' => Conversation::directKey($member, $admin)]);
    $conversation->participants()->attach([$member->id, $admin->id]);
    liveChannelAnswer($admin, 'presence-conversation.'.$conversation->cuid.'.presence')->assertOk();
    $ended = liveEnded();

    app(BlocksMembers::class)->block($member, $admin);

    expect(liveSorted($ended))->toBe(liveCuids($member, $admin));
    // The page connects again: the typing signal of the conversation is not for a pair with a block
    liveChannelAnswer($admin, 'presence-conversation.'.$conversation->cuid.'.presence')->assertForbidden();
    liveChannelAnswer($member, 'presence-conversation.'.$conversation->cuid.'.presence')->assertForbidden();
});

test('the account: a password that changes or is reset, the sign-out, the deletion', function () {
    $ended = liveEnded();
    $member = $this->c['member'];

    newBrowser();
    $this->post('/login', ['email' => $member->email, 'password' => 'password'])->assertRedirect();
    app('auth')->forgetGuards();
    $this->put('/user/password', ['current_password' => 'password', 'password' => 'A-new-password-of-2026!', 'password_confirmation' => 'A-new-password-of-2026!'])->assertRedirect();
    expect($ended->getArrayCopy())->toBe([$member->cuid]);

    app('auth')->forgetGuards();
    $this->post('/logout')->assertRedirect();
    expect($ended->getArrayCopy())->toBe([$member->cuid, $member->cuid]);

    $admin = $this->c['admin'];
    app(ResetsUserPasswords::class)->reset($admin->fresh(), ['password' => 'Another-new-password-of-2026!', 'password_confirmation' => 'Another-new-password-of-2026!']);
    expect($ended->getArrayCopy())->toBe([$member->cuid, $member->cuid, $admin->cuid]);

    $outsider = $this->c['outsider'];
    newBrowser();
    $this->actingAs($outsider)->deleteJson(ApiRoutes::path(ApiRoutes::USER), ['password' => 'password'])->assertSuccessful();
    expect($ended->getArrayCopy())->toBe([$member->cuid, $member->cuid, $admin->cuid, $outsider->cuid]);
});

// ---------------------------------------------------------------------------------------------------------------------
// The WebSocket server limits the size and the rate of the messages of a client
// ---------------------------------------------------------------------------------------------------------------------

/**
 * The application of Reverb as config/reverb.php describes it, with these values of the environment (null: not set, the
 * default of the file). The values of the environment of the machine that runs the tests do not count
 *
 * @param  array<string, string|null>  $environment
 */
function liveReverbApplication(array $environment = []): Application
{
    $environment = ['REVERB_APP_MAX_MESSAGE_SIZE' => null, 'REVERB_APP_MAX_CONNECTIONS' => null, ...$environment];
    $saved = [];
    foreach ($environment as $name => $value) {
        $saved[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];
        unset($_SERVER[$name], $_ENV[$name]);
        putenv($name);
        if ($value !== null) {
            $_SERVER[$name] = $value;
        }
    }
    try {
        $app = (require config_path('reverb.php'))['apps']['apps'][0];
    } finally {
        foreach ($saved as $name => [$server, $env, $put]) {
            unset($_SERVER[$name], $_ENV[$name]);
            if ($server !== null) {
                $_SERVER[$name] = $server;
            }
            if ($env !== null) {
                $_ENV[$name] = $env;
            }
            putenv($put === false ? $name : $name.'='.$put);
        }
    }

    return (new ConfigApplicationProvider(collect([[...$app, 'key' => 'test-key', 'secret' => 'test-secret', 'app_id' => 'test-app']])))->findByKey('test-key');
}

test('the WebSocket server limits the size and the rate of the messages of a client', function () {
    $app = liveReverbApplication();

    expect($app->maxMessageSize())->toBe(10_000)
        ->and($app->usesRateLimiting())->toBeTrue()
        ->and($app->rateLimiting())->toBe(['enabled' => true, 'max_attempts' => 30, 'decay_seconds' => 1, 'terminate_on_limit' => true])
        ->and($app->hasMaxConnectionLimit())->toBeFalse();

    expect(liveReverbApplication(['REVERB_APP_MAX_CONNECTIONS' => '500'])->maxConnections())->toBe(500);

    // The example files hold the documented value
    foreach (['.env.example', '.env.production.example'] as $file) {
        expect((string) file_get_contents(base_path($file)))->toContain("\nREVERB_APP_MAX_MESSAGE_SIZE=10000\n")->toContain("\nREVERB_APP_MAX_CONNECTIONS=\n")
            // The server events carry identifiers only: a request of the HTTP API of the server stays small
            ->toContain("\nREVERB_MAX_REQUEST_SIZE=100000\n");
    }
});
