<?php

use App\ApiRoutes;
use App\Broadcasting\CommunityOnlineChannel;
use App\Broadcasting\SpacePresenceChannel;
use App\Constants;
use App\Contracts\Realtime\ReadsPresenceChannels;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\AccessGroup;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Sanctum\Sanctum;

/*
 * Presence (#1187, ADR 0027): who is online, who is in a chat space, and members who hide it.
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $this->chat = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $this->community->id]))
        ->create(['type' => KnowiiSpaceType::Chat]);
});

/**
 * A WebSocket server that answers with these members for every channel
 *
 * @param  list<string>  $cuids
 */
function fakePresence(array $cuids): void
{
    app()->instance(ReadsPresenceChannels::class, new class($cuids) implements ReadsPresenceChannels
    {
        /** @var list<string> */
        public array $channels = [];

        /** @param list<string> $cuids */
        public function __construct(private array $cuids) {}

        public function memberCuids(string $channel): array
        {
            $this->channels[] = $channel;

            return $this->cuids;
        }
    });
}

function hide(User $user): User
{
    $user->forceFill(['show_online_status' => false])->save();

    return $user->refresh();
}

test('presence channels identify members by cuid, never by the internal id', function () {
    expect($this->c['member']->getAuthIdentifierForBroadcasting())->toBe($this->c['member']->cuid);
});

test('the presence channels are registered with their classes', function () {
    $channels = Broadcast::driver()->getChannels();

    expect($channels->get(Constants::WS_CHANNEL_COMMUNITY_ONLINE))->toBe(CommunityOnlineChannel::class)
        ->and($channels->get(Constants::WS_CHANNEL_COMMUNITY_SPACE_PRESENCE))->toBe(SpacePresenceChannel::class);
});

test('members join the online channel of their community with their public summary only', function () {
    $info = (new CommunityOnlineChannel)->join($this->c['member'], $this->community->cuid);

    expect($info)->toBe([
        'cuid' => $this->c['member']->cuid,
        'name' => $this->c['member']->name,
        'username' => $this->c['member']->username,
        'avatar_url' => null,
    ]);
});

test('outsiders and members who hide their status never join the online channel', function () {
    $channel = new CommunityOnlineChannel;

    expect($channel->join($this->c['outsider'], $this->community->cuid))->toBeFalse()
        ->and($channel->join(hide($this->c['member']), $this->community->cuid))->toBeFalse()
        ->and($channel->join($this->c['admin'], 'unknown-community'))->toBeFalse();
});

test('the presence channel of a space is for the chat spaces that the member can read', function () {
    $channel = new SpacePresenceChannel;
    $discussion = Space::factory()->inGroup($this->chat->spaceGroup)->create();
    $restricted = Space::factory()->inGroup($this->chat->spaceGroup)->create(['type' => KnowiiSpaceType::Chat, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $restricted->accessGroups()->attach(AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Buyers']));

    expect($channel->join($this->c['member'], $this->community->cuid, $this->chat->cuid))->toBeArray()
        ->and($channel->join($this->c['member'], $this->community->cuid, $discussion->cuid))->toBeFalse()
        ->and($channel->join($this->c['member'], $this->community->cuid, $restricted->cuid))->toBeFalse()
        ->and($channel->join($this->c['owner'], $this->community->cuid, $restricted->cuid))->toBeArray()
        ->and($channel->join($this->c['outsider'], $this->community->cuid, $this->chat->cuid))->toBeFalse()
        ->and($channel->join(hide($this->c['moderator']), $this->community->cuid, $this->chat->cuid))->toBeFalse();
});

test('a member hides and shows their online status', function () {
    $this->actingAs($this->c['member']);

    $this->patchJson(ApiRoutes::path(ApiRoutes::USER_ONLINE_STATUS), ['show_online_status' => false])
        ->assertOk()
        ->assertJsonPath('data.show_online_status', false);
    expect($this->c['member']->refresh()->show_online_status)->toBeFalse();

    $this->patchJson(ApiRoutes::path(ApiRoutes::USER_ONLINE_STATUS), ['show_online_status' => true])
        ->assertOk()
        ->assertJsonPath('data.show_online_status', true);

    $this->patchJson(ApiRoutes::path(ApiRoutes::USER_ONLINE_STATUS), ['show_online_status' => 'maybe'])->assertStatus(400);
});

test('a read-only token cannot change the online status', function () {
    Sanctum::actingAs($this->c['member'], ['user:read']);

    $this->patchJson(ApiRoutes::path(ApiRoutes::USER_ONLINE_STATUS), ['show_online_status' => false])->assertForbidden();
});

test('members list who is online: hidden members and outsiders never show, by name', function () {
    $this->c['admin']->forceFill(['name' => 'Zoe'])->save();
    $this->c['member']->forceFill(['name' => 'Ada'])->save();
    hide($this->c['moderator']);
    fakePresence([$this->c['admin']->cuid, $this->c['member']->cuid, $this->c['moderator']->cuid, $this->c['outsider']->cuid, 'unknown']);

    // A hidden member still sees the others (ADR 0027)
    $this->actingAs($this->c['moderator'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_ONLINE_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Ada')
        ->assertJsonPath('data.1.name', 'Zoe')
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('data.0.id')
        ->assertJsonMissingPath('data.0.email');

    expect(app(ReadsPresenceChannels::class)->channels)->toBe(['community.'.$this->community->cuid.'.online']);

    $this->actingAs($this->c['outsider'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_ONLINE_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]))
        ->assertForbidden();
});

test('members list who is in a chat space, without the members who cannot read it', function () {
    $restricted = Space::factory()->inGroup($this->chat->spaceGroup)->create(['type' => KnowiiSpaceType::Chat, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $group = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Buyers']);
    $restricted->accessGroups()->attach($group);
    $group->members()->attach($this->c['admin']);
    fakePresence([$this->c['admin']->cuid, $this->c['member']->cuid]);
    $path = fn (Space $space) => ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_ONLINE_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid]);

    $this->actingAs($this->c['owner'])->getJson($path($restricted))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.cuid', $this->c['admin']->cuid);

    $this->actingAs($this->c['member'])->getJson($path($this->chat))->assertOk()->assertJsonCount(2, 'data');
    $this->actingAs($this->c['member'])->getJson($path($restricted))->assertForbidden();

    $discussion = Space::factory()->inGroup($this->chat->spaceGroup)->create();
    $this->actingAs($this->c['member'])->getJson($path($discussion))->assertStatus(422);
});

test('without a WebSocket server, nobody is online', function () {
    $this->actingAs($this->c['member'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_ONLINE_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
