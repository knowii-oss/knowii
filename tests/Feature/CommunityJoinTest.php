<?php

use App\ApiRoutes;
use App\Constants;
use App\Contracts\CommunityMembers\JoinsCommunities;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCommunityJoinPolicy;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiModeratorPermission;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Exceptions\BusinessException;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\CommunityJoinBlock;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

/*
 * Who can join a community (#1486, ADR 0044): the setting, the join action, and the join block of a member whom a manager
 * removed.
 */

const JOIN_BLOCKED_MESSAGE = 'You cannot join this community by yourself. Ask its managers for an invitation.';

beforeEach(function () {
    $this->c = communityWithMembers();
    Event::fake([CommunityMembersChanged::class]);
});

function joinCommunityPath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERSHIP, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);
}

function joinPolicyPath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_JOIN_POLICY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);
}

function joinBlocksPath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_JOIN_BLOCKS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);
}

function joinBlockPath(Community $community, User $user): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_JOIN_BLOCK, [ApiRoutes::PARAM_COMMUNITY => $community->cuid, ApiRoutes::PARAM_MEMBER => $user->cuid]);
}

function joinCommunityMemberPath(Community $community, User $member): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBER, [ApiRoutes::PARAM_COMMUNITY => $community->cuid, ApiRoutes::PARAM_MEMBER => $member->cuid]);
}

function joinCommunityOpen(): void
{
    test()->c['community']->update(['join_policy' => KnowiiCommunityJoinPolicy::Open]);
}

function joinCommunityIsMember(User $user, Community $community): bool
{
    // actingAs() keeps the model in memory, with its cached communities
    return User::query()->findOrFail($user->id)->belongsToCommunity($community);
}

function joinCommunityPublicSpace(): void
{
    $community = test()->c['community'];
    $group = SpaceGroup::factory()->create(['community_id' => $community->id]);
    Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Public]);
}

// ---------------------------------------------------------------------------------------------------------------------
// The setting
// ---------------------------------------------------------------------------------------------------------------------

test('a community is by invitation by default (#1486)', function () {
    expect($this->c['community']->fresh()->join_policy)->toBe(KnowiiCommunityJoinPolicy::Invitation)
        ->and($this->c['community']->fresh()->isOpen())->toBeFalse();
});

test('the owner and the admins make a community open, and by invitation again (#1486)', function (string $role) {
    $this->actingAs($this->c[$role])
        ->patchJson(joinPolicyPath($this->c['community']), ['join_policy' => 'open'])
        ->assertOk()
        ->assertJsonPath('data.join_policy', 'open')
        ->assertJsonPath('data.is_member', true);
    expect($this->c['community']->fresh()->join_policy)->toBe(KnowiiCommunityJoinPolicy::Open);

    $this->actingAs($this->c[$role])
        ->patchJson(joinPolicyPath($this->c['community']), ['join_policy' => 'invitation'])
        ->assertOk()
        ->assertJsonPath('data.join_policy', 'invitation');
    expect($this->c['community']->fresh()->join_policy)->toBe(KnowiiCommunityJoinPolicy::Invitation);
})->with(['owner', 'admin']);

test('moderators, members and outsiders cannot change who can join (#1486)', function (string $role) {
    $this->actingAs($this->c[$role])
        ->patchJson(joinPolicyPath($this->c['community']), ['join_policy' => 'open'])
        ->assertForbidden();

    expect($this->c['community']->fresh()->join_policy)->toBe(KnowiiCommunityJoinPolicy::Invitation);
})->with(['moderator', 'member', 'outsider']);

test('a moderator with every right still cannot change who can join (#1486)', function () {
    $this->c['community']->update(['moderator_permissions' => array_map(fn () => true, KnowiiModeratorPermission::defaults())]);

    $this->actingAs($this->c['moderator'])->patchJson(joinPolicyPath($this->c['community']), ['join_policy' => 'open'])->assertForbidden();
});

test('the join policy is required and one of the two values (#1486)', function (array $input) {
    $this->actingAs($this->c['owner'])
        ->patchJson(joinPolicyPath($this->c['community']), $input)
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);

    expect($this->c['community']->fresh()->join_policy)->toBe(KnowiiCommunityJoinPolicy::Invitation);
})->with([
    'missing' => [[]],
    'unknown value' => [['join_policy' => 'anyone']],
    'not a string' => [['join_policy' => true]],
    'upper case' => [['join_policy' => 'OPEN']],
]);

test('a personal community refuses the setting (#1486)', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $personal = $user->ownedCommunities()->where('visibility', KnowiiCommunityVisibility::Personal)->firstOrFail();

    $this->actingAs($user)
        ->patchJson(joinPolicyPath($personal), ['join_policy' => 'open'])
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);

    expect($personal->fresh()->join_policy)->toBe(KnowiiCommunityJoinPolicy::Invitation);
});

test('the setting is read on the community, and changed with the abilities of the other settings (#1486, ADR 0022)', function () {
    $community = $this->c['community'];
    $url = ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);

    Sanctum::actingAs($this->c['owner'], ['communities:read']);
    $this->getJson($url)->assertOk()->assertJsonPath('data.join_policy', 'invitation');
    $this->patchJson(joinPolicyPath($community), ['join_policy' => 'open'])->assertForbidden();

    Sanctum::actingAs($this->c['owner'], ['communities:write']);
    $this->patchJson(joinPolicyPath($community), ['join_policy' => 'open'])->assertOk();
});

test('the settings page tells owners and admins whether they may change who can join (#1486)', function (string $role, bool $allowed) {
    $this->c['community']->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'manage_spaces' => true]]);

    $this->actingAs($this->c[$role])
        ->get(route('communities.settings', ['communitySlug' => $this->c['community']->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Communities/Settings')
            ->where('permissions.canManageJoinPolicy', $allowed)
            ->where('community.join_policy', 'invitation'));
})->with([['owner', true], ['admin', true], ['moderator', false]]);

// ---------------------------------------------------------------------------------------------------------------------
// Joining
// ---------------------------------------------------------------------------------------------------------------------

test('a signed-in person with a verified address joins an open community as a member, and gets the community as a member sees it (#1486)', function () {
    joinCommunityOpen();
    $community = $this->c['community'];
    $before = $community->users()->count();

    $this->actingAs($this->c['outsider'])
        ->postJson(joinCommunityPath($community))
        ->assertCreated()
        ->assertJsonPath('data.cuid', $community->cuid)
        ->assertJsonPath('data.is_member', true)
        ->assertJsonPath('data.join_policy', 'open')
        ->assertJsonPath('data.member_count', $before + 2)
        ->assertJsonStructure(['data' => ['moderator_permissions', 'direct_messages_enabled']]);

    expect($community->getMemberById($this->c['outsider']->id)?->role)->toBe(KnowiiCommunityMemberRole::Member)
        ->and(joinCommunityIsMember($this->c['outsider'], $community))->toBeTrue();
    Event::assertDispatched(CommunityMembersChanged::class, fn (CommunityMembersChanged $event) => $event->communityCuid === $community->cuid);
});

test('joining gives no access group (#1486)', function () {
    joinCommunityOpen();
    $group = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Paid']);

    $this->actingAs($this->c['outsider'])->postJson(joinCommunityPath($this->c['community']))->assertCreated();

    expect($group->members()->count())->toBe(0);
});

test('a community by invitation refuses the join with a clear message (#1486)', function () {
    $this->actingAs($this->c['outsider'])
        ->postJson(joinCommunityPath($this->c['community']))
        ->assertStatus(422)
        ->assertJsonPath('type', KnowiiApiResponseType::BusinessIssue->value)
        ->assertJsonPath('message', 'This community is by invitation only. Ask its managers for an invitation');

    expect(joinCommunityIsMember($this->c['outsider'], $this->c['community']))->toBeFalse();
    Event::assertNotDispatched(CommunityMembersChanged::class);
});

test('a personal community refuses the join, whatever its stored setting (#1486)', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $personal = $user->ownedCommunities()->where('visibility', KnowiiCommunityVisibility::Personal)->firstOrFail();
    $personal->update(['join_policy' => KnowiiCommunityJoinPolicy::Open]);

    $this->actingAs($this->c['outsider'])->postJson(joinCommunityPath($personal))->assertStatus(422);

    expect(joinCommunityIsMember($this->c['outsider'], $personal))->toBeFalse();
});

test('a member, an admin and the owner cannot join again: no second row, no event (#1486)', function (string $role) {
    joinCommunityOpen();
    $rows = $this->c['community']->users()->count();

    $this->actingAs($this->c[$role])
        ->postJson(joinCommunityPath($this->c['community']))
        ->assertStatus(422)
        ->assertJsonPath('message', 'You are already a member of this community');

    expect($this->c['community']->users()->count())->toBe($rows);
    Event::assertNotDispatched(CommunityMembersChanged::class);
})->with(['owner', 'admin', 'moderator', 'member']);

test('a person whom a manager removed cannot join by themselves, with a neutral message (#1486)', function () {
    joinCommunityOpen();
    $community = $this->c['community'];
    $this->actingAs($this->c['admin'])->deleteJson(joinCommunityMemberPath($community, $this->c['member']))->assertNoContent();
    Event::fake([CommunityMembersChanged::class]);

    $this->app['auth']->forgetGuards();
    $this->actingAs(User::query()->findOrFail($this->c['member']->id))
        ->postJson(joinCommunityPath($community))
        ->assertStatus(422)
        ->assertJsonPath('type', KnowiiApiResponseType::BusinessIssue->value)
        ->assertJsonPath('message', JOIN_BLOCKED_MESSAGE);

    expect(joinCommunityIsMember($this->c['member'], $community))->toBeFalse();
    Event::assertNotDispatched(CommunityMembersChanged::class);
});

test('an unverified address cannot join: the API refuses it, and so does the action (#1486)', function () {
    joinCommunityOpen();
    $unverified = User::factory()->unverified()->withUserProfile()->create();

    $this->actingAs($unverified)->postJson(joinCommunityPath($this->c['community']))->assertForbidden();
    expect(fn () => app(JoinsCommunities::class)->join($unverified, $this->c['community']))
        ->toThrow(BusinessException::class, 'Verify your email address before you join a community');

    expect(joinCommunityIsMember($unverified, $this->c['community']))->toBeFalse();
});

test('a visitor cannot join, an unknown community gives a 404 (#1486)', function () {
    joinCommunityOpen();

    $this->postJson(joinCommunityPath($this->c['community']))->assertUnauthorized();
    $this->actingAs($this->c['outsider'])
        ->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERSHIP, [ApiRoutes::PARAM_COMMUNITY => 'unknowncuid']))
        ->assertNotFound();
});

test('a token needs the ability to write communities to join (#1486, ADR 0022)', function () {
    joinCommunityOpen();

    Sanctum::actingAs($this->c['outsider'], ['communities:read']);
    $this->postJson(joinCommunityPath($this->c['community']))->assertForbidden();
    expect(joinCommunityIsMember($this->c['outsider'], $this->c['community']))->toBeFalse();

    Sanctum::actingAs($this->c['outsider'], ['communities:write']);
    $this->postJson(joinCommunityPath($this->c['community']))->assertCreated();
});

test('joining is limited per minute and per person (#1486)', function () {
    joinCommunityOpen();
    RateLimiter::clear('user:'.$this->c['outsider']->id);
    $this->actingAs($this->c['outsider']);

    // The first request joins, the next ones answer 422 ("already a member"): they all count
    for ($i = 0; $i < Constants::JOINS_PER_MINUTE; $i++) {
        $this->postJson(joinCommunityPath($this->c['community']));
    }
    $this->postJson(joinCommunityPath($this->c['community']))->assertStatus(429);
});

test('after joining, the member reads the members-only spaces (#1486)', function () {
    joinCommunityOpen();
    $community = $this->c['community'];
    $url = route('communities.show', ['communitySlug' => $community->slug]);

    $this->actingAs($this->c['outsider'])->get($url)->assertForbidden();
    $this->postJson(joinCommunityPath($community))->assertCreated();
    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('Communities/Show'));
});

// ---------------------------------------------------------------------------------------------------------------------
// The join block
// ---------------------------------------------------------------------------------------------------------------------

test('removing a member writes the join block, leaving does not (#1486)', function () {
    $community = $this->c['community'];

    $this->actingAs($this->c['moderator'])->deleteJson(joinCommunityMemberPath($community, $this->c['member']))->assertForbidden();
    expect(CommunityJoinBlock::query()->count())->toBe(0);

    $this->actingAs($this->c['admin'])->deleteJson(joinCommunityMemberPath($community, $this->c['member']))->assertNoContent();
    $this->actingAs($this->c['moderator'])->deleteJson(joinCommunityPath($community))->assertNoContent();

    $blocks = CommunityJoinBlock::query()->get();
    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->community_id)->toBe($community->id)
        ->and($blocks[0]->user_id)->toBe($this->c['member']->id)
        ->and($blocks[0]->created_at)->not->toBeNull();
});

test('a member who left can join an open community again (#1486)', function () {
    joinCommunityOpen();
    $community = $this->c['community'];

    $this->actingAs($this->c['member'])->deleteJson(joinCommunityPath($community))->assertNoContent();
    $this->app['auth']->forgetGuards();
    $this->actingAs(User::query()->findOrFail($this->c['member']->id))->postJson(joinCommunityPath($community))->assertCreated();

    expect(joinCommunityIsMember($this->c['member'], $community))->toBeTrue();
});

test('removing the same person twice keeps one block, and a block of another community does not count (#1486)', function () {
    joinCommunityOpen();
    $other = Community::factory()->create(['owner_id' => $this->c['owner']->id, 'join_policy' => KnowiiCommunityJoinPolicy::Open]);
    $this->actingAs($this->c['owner']);

    $this->deleteJson(joinCommunityMemberPath($this->c['community'], $this->c['member']))->assertNoContent();
    $this->deleteJson(joinCommunityMemberPath($this->c['community'], $this->c['member']))->assertNotFound();
    expect(CommunityJoinBlock::query()->count())->toBe(1);

    // Blocked here, free there
    $this->app['auth']->forgetGuards();
    $this->actingAs(User::query()->findOrFail($this->c['member']->id))->postJson(joinCommunityPath($other))->assertCreated();
});

test('an accepted invitation ends the block, and the person is a member again (#1486)', function () {
    $community = $this->c['community'];
    $this->actingAs($this->c['owner'])->deleteJson(joinCommunityMemberPath($community, $this->c['member']))->assertNoContent();
    expect(CommunityJoinBlock::query()->count())->toBe(1);

    $invitation = $community->communityInvitations()->create(['email' => $this->c['member']->email, 'role' => KnowiiCommunityMemberRole::Member]);
    $link = URL::temporarySignedRoute('community-invitations.accept', $invitation->expiresAt(), ['invitation' => $invitation]);
    $this->app['auth']->forgetGuards();
    $this->actingAs(User::query()->findOrFail($this->c['member']->id))->get($link)->assertRedirect();

    expect(CommunityJoinBlock::query()->count())->toBe(0)
        ->and(joinCommunityIsMember($this->c['member'], $community))->toBeTrue();
});

test('an invitation that is not accepted yet keeps the block (#1486)', function () {
    $community = $this->c['community'];
    $this->actingAs($this->c['owner'])->deleteJson(joinCommunityMemberPath($community, $this->c['member']))->assertNoContent();
    $community->communityInvitations()->create(['email' => $this->c['member']->email, 'role' => KnowiiCommunityMemberRole::Member]);

    expect(CommunityJoinBlock::query()->count())->toBe(1);
});

test('"May join again" lets the person join by themselves again (#1486)', function (string $role) {
    joinCommunityOpen();
    $community = $this->c['community'];
    $this->c['community']->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'manage_members' => true]]);
    $this->actingAs($this->c['owner'])->deleteJson(joinCommunityMemberPath($community, $this->c['member']))->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->actingAs(User::query()->findOrFail($this->c[$role]->id))
        ->deleteJson(joinBlockPath($community, $this->c['member']))
        ->assertNoContent();
    expect(CommunityJoinBlock::query()->count())->toBe(0);

    $this->app['auth']->forgetGuards();
    $this->actingAs(User::query()->findOrFail($this->c['member']->id))->postJson(joinCommunityPath($community))->assertCreated();
})->with(['owner', 'admin', 'moderator']);

test('only managers of the members lift a block or read the list (#1486)', function (string $role, bool $moderatorsManage) {
    $community = $this->c['community'];
    $community->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'manage_members' => $moderatorsManage]]);
    $this->actingAs($this->c['owner'])->deleteJson(joinCommunityMemberPath($community, $this->c['member']))->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->actingAs(User::query()->findOrFail($this->c[$role]->id));
    $this->getJson(joinBlocksPath($community))->assertForbidden();
    $this->deleteJson(joinBlockPath($community, $this->c['member']))->assertForbidden();

    expect(CommunityJoinBlock::query()->count())->toBe(1);
})->with([
    'a moderator without the right' => ['moderator', false],
    'a member' => ['member', true],
    'an outsider' => ['outsider', true],
]);

test('the managers read the removed people, newest first, without any other data (#1486)', function () {
    $community = $this->c['community'];
    $this->actingAs($this->c['owner']);
    $this->deleteJson(joinCommunityMemberPath($community, $this->c['member']))->assertNoContent();
    $this->deleteJson(joinCommunityMemberPath($community, $this->c['moderator']))->assertNoContent();

    $response = $this->getJson(joinBlocksPath($community))->assertOk();
    $response->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.cuid', $this->c['moderator']->cuid)
        ->assertJsonPath('data.1.cuid', $this->c['member']->cuid)
        ->assertJsonStructure(['data' => [['cuid', 'name', 'username', 'avatar_url', 'removed_at']]]);
    expect(array_keys($response->json('data.0')))->toBe(['cuid', 'name', 'username', 'avatar_url', 'removed_at'])
        ->and((string) $response->getContent())->not->toContain($this->c['member']->email);
});

test('lifting a block that does not exist gives a 404, for an unknown person too (#1486)', function () {
    $this->actingAs($this->c['owner']);

    $this->deleteJson(joinBlockPath($this->c['community'], $this->c['member']))->assertNotFound();
    $this->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY_JOIN_BLOCK, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_MEMBER => 'unknowncuid']))->assertNotFound();
});

test('a block of another community cannot be lifted from this one (#1486)', function () {
    $other = Community::factory()->create();
    CommunityJoinBlock::query()->create(['community_id' => $other->id, 'user_id' => $this->c['member']->id]);

    $this->actingAs($this->c['owner'])->deleteJson(joinBlockPath($this->c['community'], $this->c['member']))->assertNotFound();

    expect(CommunityJoinBlock::query()->count())->toBe(1);
});

test('the pair community and person is unique (#1486)', function () {
    CommunityJoinBlock::query()->create(['community_id' => $this->c['community']->id, 'user_id' => $this->c['member']->id]);

    expect(fn () => CommunityJoinBlock::query()->create(['community_id' => $this->c['community']->id, 'user_id' => $this->c['member']->id]))
        ->toThrow(QueryException::class);
});

test('the blocks go with the community and with the account of the person (#1486)', function () {
    $community = $this->c['community'];
    $other = Community::factory()->create();
    CommunityJoinBlock::query()->create(['community_id' => $community->id, 'user_id' => $this->c['member']->id]);
    CommunityJoinBlock::query()->create(['community_id' => $other->id, 'user_id' => $this->c['member']->id]);
    CommunityJoinBlock::query()->create(['community_id' => $other->id, 'user_id' => $this->c['moderator']->id]);

    $this->actingAs($this->c['member'])->delete('/user', ['password' => 'password']);
    expect(CommunityJoinBlock::query()->count())->toBe(1)
        ->and(CommunityJoinBlock::query()->where('community_id', $other->id)->count())->toBe(1);

    $other->delete();
    expect(CommunityJoinBlock::query()->count())->toBe(0);
});

test('the member export names the communities that removed the member, and the community export lists the removed people (#1486)', function () {
    Storage::fake('local');
    $community = $this->c['community'];
    $community->update(['join_policy' => KnowiiCommunityJoinPolicy::Open]);
    $this->actingAs($this->c['owner'])->deleteJson(joinCommunityMemberPath($community, $this->c['member']))->assertNoContent();

    $read = function (string $command, array $arguments): array {
        expect(Artisan::call($command, $arguments))->toBe(0);
        $path = trim(Artisan::output());
        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue();
        $files = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $files[(string) $zip->getNameIndex($index)] = (string) $zip->getFromIndex($index);
        }
        $zip->close();

        return $files;
    };

    $member = $read('users:export', ['email' => $this->c['member']->email]);
    $memberships = json_decode($member['memberships.json'], true);
    expect($memberships['join_blocks'])->toHaveCount(1)
        ->and($memberships['join_blocks'][0]['community'])->toBe($community->cuid)
        ->and($memberships['join_blocks'][0]['community_slug'])->toBe($community->slug)
        ->and($memberships['join_blocks'][0]['removed_at'])->not->toBeNull()
        ->and($member['README.txt'])->toContain('cannot join again by yourself');

    // The export of another person does not hold it
    $moderator = $read('users:export', ['email' => $this->c['moderator']->email]);
    expect(json_decode($moderator['memberships.json'], true)['join_blocks'])->toBe([]);

    $owner = $read('communities:export', ['slug' => $community->slug]);
    $export = json_decode($owner['community.json'], true);
    expect($export['community']['join_policy'])->toBe('open')
        ->and($export['join_blocks'])->toHaveCount(1)
        ->and($export['join_blocks'][0]['user'])->toBe($this->c['member']->cuid)
        ->and($export['join_blocks'][0]['username'])->toBe($this->c['member']->username);
});

// ---------------------------------------------------------------------------------------------------------------------
// What a visitor and an outsider read
// ---------------------------------------------------------------------------------------------------------------------

test('the public shape of the community carries the join policy, on the API and in the props of the page (#1486)', function (string $policy) {
    joinCommunityPublicSpace();
    $this->c['community']->update(['join_policy' => KnowiiCommunityJoinPolicy::from($policy)]);
    $url = ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]);
    $page = route('communities.show', ['communitySlug' => $this->c['community']->slug]);

    $this->app['auth']->forgetGuards();
    $this->getJson($url)->assertOk()->assertJsonPath('data.join_policy', $policy)->assertJsonPath('data.is_member', false);
    $this->get($page)->assertOk()->assertInertia(fn ($inertia) => $inertia->component('Communities/Public')->where('community.join_policy', $policy));

    $this->actingAs($this->c['outsider'])->getJson($url)->assertOk()->assertJsonPath('data.join_policy', $policy);
    $this->get($page)->assertOk()->assertInertia(fn ($inertia) => $inertia->component('Communities/Public')->where('community.join_policy', $policy));
})->with(['invitation', 'open']);

test('the public shape still holds nothing more than the join policy (#1486)', function () {
    joinCommunityPublicSpace();
    joinCommunityOpen();

    $this->app['auth']->forgetGuards();
    $response = $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]))->assertOk();

    expect(array_keys($response->json('data')))->toBe(['cuid', 'name', 'slug', 'description', 'visibility', 'join_policy', 'is_member']);
});

test('the space page of a public space carries the join policy for a visitor (#1486)', function () {
    joinCommunityPublicSpace();
    joinCommunityOpen();
    $space = Space::query()->where('community_id', $this->c['community']->id)->firstOrFail();

    $this->app['auth']->forgetGuards();
    $this->get(route('spaces.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $space->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('community.join_policy', 'open'));
});

test('an open community without a public space: the join works on the API, and the page stays closed (#1486)', function () {
    joinCommunityOpen();
    $url = route('communities.show', ['communitySlug' => $this->c['community']->slug]);

    $this->actingAs($this->c['outsider'])->get($url)->assertForbidden();
    $this->postJson(joinCommunityPath($this->c['community']))->assertCreated();
});

test('a visitor who signs up comes back to the page of the community, and the person joins with one click of their own (#1486)', function () {
    joinCommunityPublicSpace();
    joinCommunityOpen();
    $url = route('communities.show', ['communitySlug' => $this->c['community']->slug]);

    $this->app['auth']->forgetGuards();
    $this->get($url)->assertOk();
    $this->post('/register', ['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'password' => 'a-Long-Password-9!', 'password_confirmation' => 'a-Long-Password-9!', 'terms' => true])
        ->assertRedirect($url);

    // The new account has to verify its address: the page sends it there, and the link of the email comes back here
    $grace = User::query()->where('email', 'grace@example.com')->firstOrFail();
    expect($grace->hasVerifiedEmail())->toBeFalse();
    $this->get($url)->assertRedirect(route('verification.notice'));
    $verification = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $grace->getKey(), 'hash' => sha1($grace->getEmailForVerification())]);
    $this->get($verification)->assertRedirect($url);

    // Not a member yet: the person presses "Join"
    expect(joinCommunityIsMember($grace, $this->c['community']))->toBeFalse();
    $this->postJson(joinCommunityPath($this->c['community']))->assertCreated();
    expect(joinCommunityIsMember($grace, $this->c['community']))->toBeTrue();
});
