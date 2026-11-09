<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\SpaceTree;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $this->spaceGroup = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->group = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Paid members']);
    $this->paid = Space::factory()->inGroup($this->spaceGroup)->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $this->paid->accessGroups()->attach($this->group);
    $this->insider = User::factory()->withUserProfile()->create();
    $this->c['community']->users()->attach($this->insider, ['role' => 'member']);
    $this->group->members()->attach($this->insider);
});

function accessGroupsPath(Community $community, ?AccessGroup $group = null, string $route = ApiRoutes::COMMUNITY_ACCESS_GROUPS, ?string $member = null): string
{
    return ApiRoutes::path($route, array_filter([
        ApiRoutes::PARAM_COMMUNITY => $community->cuid,
        ApiRoutes::PARAM_ACCESS_GROUP => $group?->cuid,
        ApiRoutes::PARAM_MEMBER => $member,
    ]));
}

function canView(?User $user, Space $space): bool
{
    return Gate::forUser($user)->check('view', $space->fresh());
}

test('a groups space opens to the members of its groups, the owner and the admins only (#1241)', function () {
    expect(canView($this->insider->fresh(), $this->paid))->toBeTrue()
        ->and(canView($this->c['owner'], $this->paid))->toBeTrue()
        ->and(canView($this->c['admin'], $this->paid))->toBeTrue()
        ->and(canView($this->c['moderator'], $this->paid))->toBeFalse()
        ->and(canView($this->c['member'], $this->paid))->toBeFalse()
        ->and(canView($this->c['outsider'], $this->paid))->toBeFalse()
        ->and(canView(null, $this->paid))->toBeFalse();
});

test('the navigation hides a groups space from the members outside its groups (#1241)', function () {
    $names = fn (?User $user) => SpaceTree::for($this->c['community'], $user)->flatMap(fn ($group) => $group->spaces->pluck('cuid'))->all();

    expect($names($this->c['member']))->not->toContain($this->paid->cuid)
        ->and($names($this->insider->fresh()))->toContain($this->paid->cuid)
        ->and($names($this->c['admin']))->toContain($this->paid->cuid);
});

test('members outside the groups can neither read nor post in a groups space (#1241)', function () {
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POSTS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->paid->cuid]);

    $this->actingAs($this->c['member'])->getJson($path)->assertForbidden();
    $this->actingAs($this->c['member'])->postJson($path, ['title' => 'Hello there', 'body' => 'Text'])->assertForbidden();
    expect(Gate::forUser($this->c['member'])->check('contribute', $this->paid->fresh()))->toBeFalse()
        ->and(Gate::forUser($this->insider->fresh())->check('contribute', $this->paid->fresh()))->toBeTrue();
});

test('owner and admins manage the groups; moderators and members cannot (#1241)', function () {
    $this->actingAs($this->c['admin'])->postJson(accessGroupsPath($this->c['community']), ['name' => ' Team ', 'description' => 'The staff'])
        ->assertCreated()->assertJsonPath('data.name', 'Team')->assertJsonPath('data.members_count', 0);
    $this->actingAs($this->c['admin'])->postJson(accessGroupsPath($this->c['community']), ['name' => 'Team'])->assertStatus(400);

    $this->actingAs($this->c['moderator'])->postJson(accessGroupsPath($this->c['community']), ['name' => 'Mods'])->assertForbidden();
    $this->actingAs($this->c['member'])->getJson(accessGroupsPath($this->c['community']))->assertForbidden();

    $this->actingAs($this->c['owner'])->patchJson(accessGroupsPath($this->c['community'], $this->group, ApiRoutes::COMMUNITY_ACCESS_GROUP), ['name' => 'Supporters'])
        ->assertOk()->assertJsonPath('data.name', 'Supporters')->assertJsonPath('data.members_count', 1)->assertJsonPath('data.spaces_count', 1);

    $this->actingAs($this->c['owner'])->getJson(accessGroupsPath($this->c['community']))->assertOk()->assertJsonCount(2, 'data');
});

test('moderators with the "manage members" right add and remove group members (#1241)', function () {
    $path = accessGroupsPath($this->c['community'], $this->group, ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBERS);

    $this->actingAs($this->c['moderator'])->postJson($path, ['username' => $this->c['member']->username])->assertForbidden();

    $this->c['community']->update(['moderator_permissions' => [...$this->c['community']->moderatorPermissions(), 'manage_members' => true]]);
    $this->actingAs($this->c['moderator'])->postJson($path, ['username' => $this->c['member']->username])->assertOk()->assertJsonPath('data.cuid', $this->c['member']->cuid);
    expect(canView($this->c['member']->fresh(), $this->paid))->toBeTrue();

    $this->actingAs($this->c['moderator'])->deleteJson(accessGroupsPath($this->c['community'], $this->group, ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBER, $this->c['member']->cuid))->assertNoContent();
    expect(canView($this->c['member']->fresh(), $this->paid))->toBeFalse();
});

test('a member is added by cuid, username or email, once, and only a member of the community (#1241)', function () {
    $path = accessGroupsPath($this->c['community'], $this->group, ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBERS);
    $this->actingAs($this->c['admin']);

    $this->postJson($path, ['email' => strtoupper($this->c['member']->email)])->assertOk();
    $this->postJson($path, ['member' => $this->c['member']->cuid])->assertOk();
    $this->postJson($path, ['username' => $this->c['moderator']->username])->assertOk();
    expect($this->group->members()->count())->toBe(3);

    $this->postJson($path, ['email' => $this->c['outsider']->email])->assertStatus(400)->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);
    $this->postJson($path, ['email' => 'nobody@example.com'])->assertStatus(400);
    $this->postJson($path, ['email' => $this->c['member']->email, 'username' => 'x'])->assertStatus(400);
    $this->postJson($path, [])->assertStatus(400);

    $this->getJson($path)->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('metadata.pagination.totalCount', 3);
    $this->deleteJson(accessGroupsPath($this->c['community'], $this->group, ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBER, $this->c['outsider']->cuid))->assertNotFound();
});

test('a group of another community gives a 404 (#1241)', function () {
    $other = communityWithMembers();

    $this->actingAs($other['owner'])
        ->patchJson(accessGroupsPath($other['community'], $this->group, ApiRoutes::COMMUNITY_ACCESS_GROUP), ['name' => 'Mine'])
        ->assertNotFound();
});

test('a groups space needs groups of its own community, and leaves them when it opens to all members (#1241)', function () {
    $spacesPath = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACES, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]);
    $base = ['name' => 'Paid lounge', 'type' => 'discussion', 'posting_policy' => 'members', 'space_group_cuid' => $this->spaceGroup->cuid];
    $foreign = AccessGroup::query()->create(['community_id' => Community::factory()->create()->id, 'name' => 'Elsewhere']);
    $this->actingAs($this->c['owner']);

    $this->postJson($spacesPath, [...$base, 'visibility' => 'groups'])->assertStatus(400)->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);
    $this->postJson($spacesPath, [...$base, 'visibility' => 'groups', 'access_groups' => [$foreign->cuid]])->assertStatus(400);
    $cuid = $this->postJson($spacesPath, [...$base, 'visibility' => 'groups', 'access_groups' => [$this->group->cuid]])
        ->assertCreated()
        ->assertJsonPath('data.access_groups.0.name', 'Paid members')
        ->json('data.cuid');

    $space = Space::query()->where('cuid', $cuid)->firstOrFail();
    $this->patchJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $cuid]), ['visibility' => 'members'])
        ->assertOk()->assertJsonPath('data.access_groups', []);
    expect($space->accessGroups()->count())->toBe(0);
});

test('deleting a group closes its spaces to its members; the admins still see them (#1241)', function () {
    $this->actingAs($this->c['admin'])->deleteJson(accessGroupsPath($this->c['community'], $this->group, ApiRoutes::COMMUNITY_ACCESS_GROUP))->assertNoContent();

    expect(canView($this->insider->fresh(), $this->paid))->toBeFalse()
        ->and(canView($this->c['admin'], $this->paid))->toBeTrue();
});

test('a member removed from the community leaves its groups (#1241)', function () {
    $this->c['community']->removeUser($this->insider);

    expect($this->group->members()->count())->toBe(0);
});

test('a sync token needs groups:write; communities:write is not enough (#1241)', function () {
    $path = accessGroupsPath($this->c['community'], $this->group, ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBERS);

    Sanctum::actingAs($this->c['owner'], ['communities:write']);
    $this->postJson($path, ['email' => $this->c['member']->email])->assertForbidden();

    Sanctum::actingAs($this->c['owner'], ['groups:write']);
    $this->postJson($path, ['email' => $this->c['member']->email])->assertOk();
});
