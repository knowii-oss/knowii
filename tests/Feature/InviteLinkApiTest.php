<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiModeratorPermission;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\AccessGroupRule;
use App\Models\Community;
use App\Models\CommunityJoinBlock;
use App\Models\InviteLink;
use App\Models\InviteLinkUse;
use App\Models\MemberTag;
use App\Models\User;
use App\Support\InviteLinkTexts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

/*
 * The routes of the invite links (#1391, ADR 0062): the routes of the owner and the admins, the order of their
 * refusals and their token abilities, and the routes of the person who opens a link, which never tell anything about a
 * link that does not work, and never show its name, its groups, its tags or its counts.
 */

// Texts that no answer holds by accident, with no character that JSON or HTML writes in another way
const ILAPI_LINK_NAME = 'Qxlinkname Course buyers';
const ILAPI_GROUP_NAME = 'Qxlinkgroup Cohort';
const ILAPI_TAG_NAME = 'Qxlinktag Buyer';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23T10:00:00Z'));
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $this->community->forceFill(['name' => 'Qxapi Community', 'slug' => 'qxapi-community', 'description' => 'A community of example'])->save();
    $this->group = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => ILAPI_GROUP_NAME]);
    $this->tag = MemberTag::factory()->forMembers()->create(['community_id' => $this->community->id, 'name' => ILAPI_TAG_NAME, 'color' => 'teal']);
    $this->link = InviteLink::factory()->create(['community_id' => $this->community->id, 'name' => ILAPI_LINK_NAME, 'max_uses' => 100, 'expires_at' => '2026-11-30T10:00:00Z']);
    $this->link->accessGroups()->attach($this->group->id);
    $this->link->memberTags()->attach($this->tag->id);
    $this->newcomer = User::factory()->withUserProfile()->create();
    Event::fake([CommunityMembersChanged::class, AccessGroupsChanged::class, SpaceTreeChanged::class]);
});

function ilapiPath(Community $community, ?InviteLink $link = null, string $route = ApiRoutes::COMMUNITY_INVITE_LINKS): string
{
    return ApiRoutes::path($route, array_filter([
        ApiRoutes::PARAM_COMMUNITY => $community->cuid,
        ApiRoutes::PARAM_INVITE_LINK => $link?->cuid,
    ]));
}

function ilapiTokenPath(string $token, string $route = ApiRoutes::INVITE_LINK): string
{
    return ApiRoutes::path($route, [ApiRoutes::PARAM_INVITE_LINK_TOKEN => $token]);
}

function ilapiAs(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->actingAs($user->fresh() ?? $user, 'web');
}

/**
 * @param  list<string>  $abilities
 */
function ilapiToken(User $user, array $abilities): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken($user->createToken('qx-token', $abilities)->plainTextToken);
}

function ilapiCall(TestCase $test, string $method, string $path, array $body = []): TestResponse
{
    return match ($method) {
        'get' => $test->getJson($path),
        'post' => $test->postJson($path, $body),
        'patch' => $test->patchJson($path, $body),
        'delete' => $test->deleteJson($path),
    };
}

// ---------------------------------------------------------------------------------------------------------------------
// The routes of the managers
// ---------------------------------------------------------------------------------------------------------------------

test('the owner lists the links, newest first, with their state, their address and what they give', function () {
    $this->travel(1)->minutes();
    $newer = InviteLink::factory()->off()->create(['community_id' => $this->community->id, 'name' => 'Qxlink Newer']);
    AccessGroupRule::query()->create(['access_group_id' => $this->group->id, 'entitlement_key' => 'example.paid']);

    $response = ilapiAs($this->c['owner'])->getJson(ilapiPath($this->community))->assertOk();

    expect($response->json('data.*.cuid'))->toBe([$newer->cuid, $this->link->cuid])
        ->and($response->json('data.0.status'))->toBe('off')
        ->and($response->json('data.1'))->toMatchArray([
            'cuid' => $this->link->cuid,
            'name' => ILAPI_LINK_NAME,
            'url' => url('/invite/'.$this->link->token),
            'status' => 'active',
            'active' => true,
            'expires_at' => '2026-11-30T10:00:00.000000Z',
            'max_uses' => 100,
            'uses_count' => 0,
            'access_groups' => [['cuid' => $this->group->cuid, 'name' => ILAPI_GROUP_NAME, 'fed_by_rule' => true]],
        ])
        ->and($response->json('data.1.member_tags.0'))->toMatchArray(['cuid' => $this->tag->cuid, 'name' => ILAPI_TAG_NAME, 'color' => 'teal', 'visibility' => 'members'])
        ->and(array_keys($response->json('data.1')))->toBe(['cuid', 'name', 'url', 'status', 'active', 'expires_at', 'max_uses', 'uses_count', 'access_groups', 'member_tags', 'created_at', 'updated_at'])
        ->and($response->getContent())->not->toContain($this->link->token_hash)
        ->and($response->getContent())->not->toContain('token_hash');
});

test('an admin creates a link: 201 with every field and its address', function () {
    $response = ilapiAs($this->c['admin'])->postJson(ilapiPath($this->community), [
        'name' => 'Course buyers',
        'access_groups' => [$this->group->cuid],
        'member_tags' => [$this->tag->cuid],
        'expires_at' => '2026-11-30T10:00:00Z',
        'max_uses' => 100,
    ])->assertCreated();

    $link = InviteLink::query()->where('cuid', $response->json('data.cuid'))->firstOrFail();
    expect($response->json('data'))->toMatchArray([
        'name' => 'Course buyers',
        'url' => url('/invite/'.$link->token),
        'status' => 'active',
        'active' => true,
        'max_uses' => 100,
        'uses_count' => 0,
        'access_groups' => [['cuid' => $this->group->cuid, 'name' => ILAPI_GROUP_NAME, 'fed_by_rule' => false]],
    ])->and($response->json('data.member_tags.0.cuid'))->toBe($this->tag->cuid)
        ->and($response->json('data.created_at'))->not->toBeNull();
});

test('the owner reads, changes and deletes a link', function () {
    ilapiAs($this->c['owner'])->getJson(ilapiPath($this->community, $this->link, ApiRoutes::COMMUNITY_INVITE_LINK))->assertOk()->assertJsonPath('data.name', ILAPI_LINK_NAME);

    ilapiAs($this->c['owner'])->patchJson(ilapiPath($this->community, $this->link, ApiRoutes::COMMUNITY_INVITE_LINK), ['active' => false, 'member_tags' => []])
        ->assertOk()
        ->assertJsonPath('data.status', 'off')
        ->assertJsonPath('data.active', false)
        ->assertJsonPath('data.member_tags', [])
        ->assertJsonPath('data.access_groups.0.cuid', $this->group->cuid)
        ->assertJsonPath('data.max_uses', 100);

    ilapiAs($this->c['owner'])->deleteJson(ilapiPath($this->community, $this->link, ApiRoutes::COMMUNITY_INVITE_LINK))->assertNoContent();
    expect(InviteLink::query()->count())->toBe(0);
});

test('the list of who joined is newest first, then by id, with the member and the date, and pages with limit and offset', function () {
    $people = User::factory()->withUserProfile()->count(3)->create();
    foreach ($people as $index => $person) {
        $this->community->users()->attach($person, ['role' => 'member']);
        $use = InviteLinkUse::query()->create(['invite_link_id' => $this->link->id, 'user_id' => $person->id]);
        // The first two at the same time: the id decides
        $use->forceFill(['created_at' => $index < 2 ? '2026-11-23T09:00:00Z' : '2026-11-23T09:30:00Z'])->save();
    }
    $other = InviteLink::factory()->create(['community_id' => $this->community->id]);
    InviteLinkUse::query()->create(['invite_link_id' => $other->id, 'user_id' => $this->c['member']->id]);

    $response = ilapiAs($this->c['owner'])->getJson(ilapiPath($this->community, $this->link, ApiRoutes::COMMUNITY_INVITE_LINK_USES).'?limit=2&offset=0')->assertOk();
    expect($response->json('data.*.member.cuid'))->toBe([$people[2]->cuid, $people[1]->cuid])
        ->and($response->json('data.0.joined_at'))->toBe('2026-11-23T09:30:00.000000Z')
        ->and(array_keys($response->json('data.0.member')))->toBe(['cuid', 'name', 'username', 'avatar_url'])
        ->and($response->json('metadata.pagination.totalCount'))->toBe(3)
        ->and($response->json('metadata.pagination.nextOffset'))->toBe(2);

    $next = ilapiAs($this->c['owner'])->getJson(ilapiPath($this->community, $this->link, ApiRoutes::COMMUNITY_INVITE_LINK_USES).'?limit=2&offset=2')->assertOk();
    expect($next->json('data.*.member.cuid'))->toBe([$people[0]->cuid]);
});

test('a moderator, with or without the right to manage members, a member and an outsider get 403 on every route of the managers', function (string $who, bool $manageMembers) {
    if ($manageMembers) {
        $this->community->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'manage_members' => true]]);
    }
    $routes = [
        ['get', ilapiPath($this->community)],
        ['post', ilapiPath($this->community)],
        ['get', ilapiPath($this->community, $this->link, ApiRoutes::COMMUNITY_INVITE_LINK)],
        ['patch', ilapiPath($this->community, $this->link, ApiRoutes::COMMUNITY_INVITE_LINK)],
        ['delete', ilapiPath($this->community, $this->link, ApiRoutes::COMMUNITY_INVITE_LINK)],
        ['get', ilapiPath($this->community, $this->link, ApiRoutes::COMMUNITY_INVITE_LINK_USES)],
    ];

    foreach ($routes as [$method, $path]) {
        $response = ilapiCall(ilapiAs($this->c[$who]), $method, $path, ['name' => 'Qxlink Moderated']);
        expect($response->status())->toBe(403, "{$method} {$path}")
            ->and($response->getContent())->not->toContain(ILAPI_LINK_NAME)
            ->and($response->getContent())->not->toContain($this->link->token);
    }
    expect(InviteLink::query()->count())->toBe(1)->and($this->link->fresh()?->active)->toBeTrue();
})->with([
    'a moderator' => ['moderator', false],
    'a moderator with manage members' => ['moderator', true],
    'a member' => ['member', false],
    'an outsider' => ['outsider', false],
]);

test('the refusals come in this order: 404 for a link of another community, then 403, then 400, then the limit', function () {
    $other = communityWithMembers();
    $foreign = InviteLink::factory()->create(['community_id' => $other['community']->id]);

    // 404 before 403: the moderator asks for a link of another community
    ilapiAs($this->c['moderator'])->patchJson(ilapiPath($this->community, $foreign, ApiRoutes::COMMUNITY_INVITE_LINK), ['name' => 'x'])->assertNotFound();
    ilapiAs($this->c['owner'])->getJson(ilapiPath($this->community, $foreign, ApiRoutes::COMMUNITY_INVITE_LINK))->assertNotFound();
    ilapiAs($this->c['owner'])->getJson(ilapiPath($this->community, $foreign, ApiRoutes::COMMUNITY_INVITE_LINK_USES))->assertNotFound();
    // 403 before 400
    ilapiAs($this->c['moderator'])->postJson(ilapiPath($this->community), ['name' => 'x'])->assertForbidden();
    // 400 before the limit
    InviteLink::factory()->count(Constants::MAX_INVITE_LINKS_PER_COMMUNITY - 1)->create(['community_id' => $this->community->id]);
    ilapiAs($this->c['owner'])->postJson(ilapiPath($this->community), ['name' => 'x'])->assertStatus(400)->assertJsonPath('errors.name.0', fn ($message) => is_string($message));
    ilapiAs($this->c['owner'])->postJson(ilapiPath($this->community), ['name' => 'Qxlink Too many'])->assertStatus(422)->assertJsonPath('message', 'A community can have at most 50 invite links');
});

test('a personal community answers a validation error on community', function () {
    $personal = Community::factory()->create(['owner_id' => $this->c['owner']->id, 'visibility' => KnowiiCommunityVisibility::Personal]);

    ilapiAs($this->c['owner'])->postJson(ilapiPath($personal), ['name' => 'Qxlink Mine'])->assertStatus(400)->assertJsonPath('errors.community.0', 'A personal community has no invite link');
});

test('the routes of the managers need the abilities of the invite links, which are not in the default set of a token', function () {
    ilapiToken($this->c['owner'], ['communities:read', 'communities:write'])->getJson(ilapiPath($this->community))->assertForbidden();
    ilapiToken($this->c['owner'], ['communities:read', 'communities:write'])->postJson(ilapiPath($this->community), ['name' => 'Qxlink Token'])->assertForbidden();
    ilapiToken($this->c['owner'], ['invite-links:read'])->getJson(ilapiPath($this->community))->assertOk();
    ilapiToken($this->c['owner'], ['invite-links:read'])->postJson(ilapiPath($this->community), ['name' => 'Qxlink Token'])->assertForbidden();
    ilapiToken($this->c['owner'], ['invite-links:write'])->postJson(ilapiPath($this->community), ['name' => 'Qxlink Token'])->assertCreated();
});

test('a token gives groups and tags on a link only with groups:write and member-tags:write', function () {
    ilapiToken($this->c['owner'], ['invite-links:write'])->postJson(ilapiPath($this->community), ['name' => 'Qxlink Token', 'access_groups' => [$this->group->cuid]])
        ->assertForbidden()->assertJsonPath('message', 'You cannot give access groups');
    ilapiToken($this->c['owner'], ['invite-links:write'])->postJson(ilapiPath($this->community), ['name' => 'Qxlink Token', 'member_tags' => [$this->tag->cuid]])
        ->assertForbidden()->assertJsonPath('message', 'You cannot give member tags');
    ilapiToken($this->c['owner'], ['invite-links:write', 'groups:write', 'member-tags:write'])->postJson(ilapiPath($this->community), ['name' => 'Qxlink Token', 'access_groups' => [$this->group->cuid], 'member_tags' => [$this->tag->cuid]])
        ->assertCreated();
});

// ---------------------------------------------------------------------------------------------------------------------
// The routes of the person who opens a link
// ---------------------------------------------------------------------------------------------------------------------

test('the read of a link gives the public shape of the community and the state join, member or blocked', function () {
    $join = ilapiAs($this->newcomer)->getJson(ilapiTokenPath($this->link->token))->assertOk();
    expect($join->json('data'))->toBe([
        'community' => [
            'cuid' => $this->community->cuid,
            'name' => 'Qxapi Community',
            'slug' => 'qxapi-community',
            'description' => 'A community of example',
            'visibility' => $this->community->visibility->value,
            'join_policy' => 'invitation',
            'is_member' => false,
        ],
        'state' => 'join',
    ]);

    $member = ilapiAs($this->c['member'])->getJson(ilapiTokenPath($this->link->token))->assertOk();
    expect($member->json('data.state'))->toBe('member')->and($member->json('data.community.is_member'))->toBeTrue()
        ->and($member->json('data.community'))->not->toHaveKey('member_count');
    expect(ilapiAs($this->c['owner'])->getJson(ilapiTokenPath($this->link->token))->json('data.state'))->toBe('member');

    CommunityJoinBlock::query()->create(['community_id' => $this->community->id, 'user_id' => $this->newcomer->id]);
    expect(ilapiAs($this->newcomer)->getJson(ilapiTokenPath($this->link->token))->json('data.state'))->toBe('blocked');
});

test('the join answers 201 with the community as a member sees it, then the member gets 422', function () {
    $response = ilapiAs($this->newcomer)->postJson(ilapiTokenPath($this->link->token, ApiRoutes::INVITE_LINK_MEMBERSHIP))->assertCreated();

    expect($response->json('data.cuid'))->toBe($this->community->cuid)
        ->and($response->json('data.is_member'))->toBeTrue()
        ->and($this->link->fresh()?->uses_count)->toBe(1);

    ilapiAs($this->newcomer)->postJson(ilapiTokenPath($this->link->token, ApiRoutes::INVITE_LINK_MEMBERSHIP))
        ->assertStatus(422)->assertJsonPath('message', 'You are already a member of this community');
    expect($this->link->fresh()?->uses_count)->toBe(1);
});

test('a person whom a manager removed gets 422 with the text of the block', function () {
    CommunityJoinBlock::query()->create(['community_id' => $this->community->id, 'user_id' => $this->newcomer->id]);

    ilapiAs($this->newcomer)->postJson(ilapiTokenPath($this->link->token, ApiRoutes::INVITE_LINK_MEMBERSHIP))
        ->assertStatus(422)->assertJsonPath('message', 'You cannot join this community by yourself. Ask its managers for an invitation.');
});

test('every link that does not work gives the same 404 as an unknown token, on the read and on the join', function (string $case) {
    $unknown = InviteLink::newToken();
    $expectedRead = ilapiAs($this->newcomer)->getJson(ilapiTokenPath($unknown))->assertNotFound();
    $expectedJoin = ilapiAs($this->newcomer)->postJson(ilapiTokenPath($unknown, ApiRoutes::INVITE_LINK_MEMBERSHIP))->assertNotFound();
    expect($expectedRead->json('message'))->toBe(InviteLinkTexts::UNUSABLE)->and($expectedJoin->getContent())->toBe($expectedRead->getContent());

    $token = $this->link->token;
    match ($case) {
        'off' => $this->link->update(['active' => false]),
        'ended' => $this->link->update(['expires_at' => '2026-11-23T09:00:00Z']),
        'full' => $this->link->forceFill(['max_uses' => 1, 'uses_count' => 1])->save(),
        'deleted' => $this->link->delete(),
        'of a personal community' => $this->community->forceFill(['visibility' => KnowiiCommunityVisibility::Personal])->save(),
        'not a token' => $token = 'not-a-token',
        'of a deleted community' => $this->community->delete(),
    };

    $read = ilapiAs($this->newcomer)->getJson(ilapiTokenPath($token))->assertNotFound();
    $join = ilapiAs($this->newcomer)->postJson(ilapiTokenPath($token, ApiRoutes::INVITE_LINK_MEMBERSHIP))->assertNotFound();

    expect($read->getContent())->toBe($expectedRead->getContent())
        ->and($join->getContent())->toBe($expectedRead->getContent())
        ->and(InviteLinkUse::query()->count())->toBe(0);
})->with(['off', 'ended', 'full', 'deleted', 'of a personal community', 'not a token', 'of a deleted community']);

test('the answers of the person never hold the name of the link, its groups, its tags, its counts, its hash or another member', function () {
    $this->link->forceFill(['uses_count' => 7])->save();
    $member = $this->c['member'];
    $answers = [
        'read' => ilapiAs($this->newcomer)->getJson(ilapiTokenPath($this->link->token))->assertOk(),
        'join' => ilapiAs($this->newcomer)->postJson(ilapiTokenPath($this->link->token, ApiRoutes::INVITE_LINK_MEMBERSHIP))->assertCreated(),
        'read as a member' => ilapiAs($this->newcomer)->getJson(ilapiTokenPath($this->link->token))->assertOk(),
    ];

    foreach ($answers as $name => $response) {
        $content = (string) $response->getContent();
        foreach ([ILAPI_LINK_NAME, ILAPI_GROUP_NAME, ILAPI_TAG_NAME, $this->group->cuid, $this->tag->cuid, $this->link->cuid, $this->link->token_hash, $this->link->token, '"uses_count"', '"max_uses"', '"expires_at"', $member->cuid, $member->username] as $secret) {
            expect($content)->not->toContain($secret);
        }
    }
});

test('the token is in no answer of the managers but the address of the link', function () {
    $content = (string) ilapiAs($this->c['owner'])->getJson(ilapiPath($this->community))->assertOk()->getContent();

    expect(substr_count($content, $this->link->token))->toBe(1)
        ->and($content)->toContain('"url":"'.str_replace('/', '\/', url('/invite/'.$this->link->token)).'"');
});

test('the read of a link is limited to 20 a minute per account, and the join to the limit of the joins', function () {
    for ($i = 0; $i < Constants::INVITE_LINK_READS_PER_MINUTE; $i++) {
        ilapiAs($this->newcomer)->getJson(ilapiTokenPath(InviteLink::newToken()))->assertNotFound();
    }
    ilapiAs($this->newcomer)->getJson(ilapiTokenPath($this->link->token))->assertStatus(429);
    // Another account has its own count
    ilapiAs($this->c['outsider'])->getJson(ilapiTokenPath($this->link->token))->assertOk();

    for ($i = 0; $i < Constants::JOINS_PER_MINUTE; $i++) {
        ilapiAs($this->c['outsider'])->postJson(ilapiTokenPath(InviteLink::newToken(), ApiRoutes::INVITE_LINK_MEMBERSHIP))->assertNotFound();
    }
    ilapiAs($this->c['outsider'])->postJson(ilapiTokenPath($this->link->token, ApiRoutes::INVITE_LINK_MEMBERSHIP))->assertStatus(429);
});

test('the routes of the person need an account with a verified address, and the abilities of the communities with a token', function () {
    $this->getJson(ilapiTokenPath($this->link->token))->assertUnauthorized();
    $this->postJson(ilapiTokenPath($this->link->token, ApiRoutes::INVITE_LINK_MEMBERSHIP))->assertUnauthorized();
    ilapiAs(User::factory()->withUserProfile()->unverified()->create())->postJson(ilapiTokenPath($this->link->token, ApiRoutes::INVITE_LINK_MEMBERSHIP))->assertForbidden();

    ilapiToken($this->newcomer, ['user:read'])->getJson(ilapiTokenPath($this->link->token))->assertForbidden();
    ilapiToken($this->newcomer, ['communities:read'])->postJson(ilapiTokenPath($this->link->token, ApiRoutes::INVITE_LINK_MEMBERSHIP))->assertForbidden();
    ilapiToken($this->newcomer, ['communities:read', 'communities:write'])->postJson(ilapiTokenPath($this->link->token, ApiRoutes::INVITE_LINK_MEMBERSHIP))->assertCreated();
});

test('the abilities of the invite links exist and are not in the default set of a new token', function () {
    expect(Jetstream::$permissions)->toContain('invite-links:read', 'invite-links:write')
        ->and(Jetstream::$defaultPermissions)->not->toContain('invite-links:read')->not->toContain('invite-links:write');
});
