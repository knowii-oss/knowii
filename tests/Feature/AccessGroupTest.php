<?php

use App\Actions\AccessGroups\AddAccessGroupMember;
use App\Actions\SpaceGroups\UpdateSpaceGroup;
use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\SpaceTree;
use Illuminate\Support\Facades\DB;
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

    $this->postJson($path, ['email' => strtoupper($this->c['member']->email)])->assertNoContent();
    $this->postJson($path, ['member' => $this->c['member']->cuid])->assertOk()->assertJsonPath('data.cuid', $this->c['member']->cuid);
    $this->postJson($path, ['username' => $this->c['moderator']->username])->assertOk()->assertJsonPath('data.username', $this->c['moderator']->username);
    expect($this->group->members()->count())->toBe(3);

    $this->postJson($path, ['member' => $this->c['outsider']->cuid])->assertStatus(400)->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);
    $this->postJson($path, ['username' => $this->c['outsider']->username])->assertStatus(400);
    $this->postJson($path, ['email' => $this->c['member']->email, 'username' => 'x'])->assertStatus(400);
    $this->postJson($path, ['email' => 'not an address'])->assertStatus(400);
    $this->postJson($path, [])->assertStatus(400);

    $this->getJson($path)->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('metadata.pagination.totalCount', 3);
    $this->deleteJson(accessGroupsPath($this->c['community'], $this->group, ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBER, $this->c['outsider']->cuid))->assertNotFound();
});

test('the owner and the admins add a member to an access group by email address, and the answer has no member', function (string $role) {
    $path = accessGroupsPath($this->c['community'], $this->group, ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBERS);
    $manager = $this->c[$role]->fresh();
    $hidden = $this->c['member'];
    $hidden->forceFill(['show_in_member_list' => false])->save();
    Event::fake([AccessGroupsChanged::class]);

    // One answer for a member, for the owner, for an account that is not a member, and for an address of nobody
    foreach ([$hidden->email, $this->c['owner']->email, $this->c['outsider']->email, 'nobody@example.com'] as $address) {
        $response = $this->actingAs($manager)->postJson($path, ['email' => $address])->assertNoContent();
        expect($response->getContent())->toBe('');
    }

    // Only the members of the community are in the group now
    expect($this->group->members()->pluck('users.id')->all())->toEqualCanonicalizing([$this->insider->id, $hidden->id, $this->c['owner']->id])
        ->and(canView($hidden->fresh(), $this->paid))->toBeTrue();
    Event::assertDispatchedTimes(AccessGroupsChanged::class, 2);

    // The same address again changes nothing and answers the same
    $this->actingAs($manager)->postJson($path, ['email' => $hidden->email])->assertNoContent();
    expect($this->group->members()->count())->toBe(3);
})->with(['owner', 'admin']);

test('a moderator adds a member to an access group by username, never by email address', function () {
    $this->c['community']->update(['moderator_permissions' => [...$this->c['community']->moderatorPermissions(), 'manage_members' => true]]);
    $path = accessGroupsPath($this->c['community'], $this->group, ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBERS);
    $moderator = $this->c['moderator']->fresh();
    Event::fake([AccessGroupsChanged::class]);

    $send = function (string $address) use ($path, $moderator): array {
        app('auth')->forgetGuards();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($moderator)->postJson($path, ['email' => $address]);
        $queries = collect(DB::getQueryLog())->pluck('query')->all();
        DB::disableQueryLog();
        $response->assertForbidden()->assertJsonPath('message', AddAccessGroupMember::EMAIL_SELECTOR_MESSAGE);

        return [$response->getContent(), $queries];
    };

    // The address of a member and an address of nobody: the same answer, given before the address is read
    $send('first@example.com');
    [$memberAnswer, $memberQueries] = $send($this->c['member']->email);
    [$nobodyAnswer, $nobodyQueries] = $send('nobody@example.com');
    expect($memberAnswer)->toBe($nobodyAnswer)
        ->and($memberQueries)->toBe($nobodyQueries)
        ->and(implode(' ', $memberQueries))->not->toContain('lower(email)')
        ->and($this->group->members()->whereKey($this->c['member']->id)->exists())->toBeFalse();
    Event::assertNotDispatched(AccessGroupsChanged::class);

    // A token follows the role of its account
    Sanctum::actingAs($moderator, ['groups:write']);
    $this->postJson($path, ['email' => $this->c['member']->email])->assertForbidden()->assertJsonPath('message', AddAccessGroupMember::EMAIL_SELECTOR_MESSAGE);

    // By username and by cuid, the moderator adds the member
    $this->postJson($path, ['username' => $this->c['member']->username])->assertOk()->assertJsonPath('data.cuid', $this->c['member']->cuid);
    $this->postJson($path, ['member' => $this->c['admin']->cuid])->assertOk()->assertJsonPath('data.cuid', $this->c['admin']->cuid);
});

test('the add and the remove of a group member have a limit per account', function () {
    $path = accessGroupsPath($this->c['community'], $this->group, ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBERS);
    $memberPath = accessGroupsPath($this->c['community'], $this->group, ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBER, $this->c['member']->cuid);

    $this->actingAs($this->c['admin']);
    foreach (range(1, Constants::ACCESS_GROUP_MEMBER_CHANGES_PER_MINUTE) as $try) {
        $try % 2 === 1 ? $this->postJson($path, ['email' => "sync{$try}@example.com"])->assertNoContent() : $this->deleteJson($memberPath)->assertNotFound();
    }
    $this->postJson($path, ['email' => $this->c['member']->email])->assertStatus(429);
    $this->postJson($path, ['member' => $this->c['member']->cuid])->assertStatus(429);
    $this->deleteJson($memberPath)->assertStatus(429);
    expect($this->group->members()->whereKey($this->c['member']->id)->exists())->toBeFalse();

    // The limit is the one of the account: another manager still adds, and the reads are not counted
    $this->getJson($path)->assertOk();
    app('auth')->forgetGuards();
    $this->actingAs($this->c['owner'])->postJson($path, ['member' => $this->c['member']->cuid])->assertOk();

    // A minute later the first manager adds again
    $this->travel(61)->seconds();
    app('auth')->forgetGuards();
    $this->actingAs($this->c['admin'])->postJson($path, ['username' => $this->c['moderator']->username])->assertOk();
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
    $this->postJson($path, ['email' => $this->c['member']->email])->assertNoContent();
    expect($this->group->members()->whereKey($this->c['member']->id)->exists())->toBeTrue();
});

test('a moderator outside the groups neither moderates nor changes nor deletes a groups space (#1325)', function () {
    $this->c['community']->update(['moderator_permissions' => [...$this->c['community']->moderatorPermissions(), 'delete_content' => true, 'manage_spaces' => true]]);
    $moderator = $this->c['moderator']->fresh();
    $post = Post::factory()->create(['space_id' => $this->paid->id, 'author_id' => $this->insider->id]);
    $spacePath = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->paid->cuid]);

    expect(Gate::forUser($moderator)->check('moderate', $this->paid->fresh()))->toBeFalse()
        ->and(Gate::forUser($moderator)->check('delete', $post->fresh()))->toBeFalse();

    $this->actingAs($moderator)->patchJson($spacePath, ['visibility' => 'members'])->assertForbidden();
    $this->actingAs($moderator)->deleteJson($spacePath)->assertForbidden();
    expect($this->paid->fresh()->visibility)->toBe(KnowiiSpaceVisibility::Groups);

    // In the group, the same moderator moderates the space
    $this->group->members()->attach($moderator);
    expect(Gate::forUser($moderator->fresh())->check('moderate', $this->paid->fresh()))->toBeTrue();
});

test('pinning a post needs the access to its space, as the other moderator actions', function () {
    $post = Post::factory()->inSpace($this->paid)->create(['author_id' => $this->insider->id, 'title' => 'Plans of the year', 'body' => 'The text of the plans', 'body_html' => '<p>The text of the plans</p>']);
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POST_PIN, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->paid->cuid, ApiRoutes::PARAM_POST => $post->cuid]);
    // The right to pin posts is on by default (ADR 0017)
    $moderator = $this->c['moderator']->fresh();

    expect(Gate::forUser($moderator)->check('pin', $post->fresh()))->toBeFalse();

    foreach ([true, false] as $pinned) {
        $response = $this->actingAs($moderator)->postJson($path, ['pinned' => $pinned])->assertForbidden();
        expect($response->json('data'))->toBeNull()
            ->and($response->getContent())->not->toContain('Plans of the year')
            ->and($response->getContent())->not->toContain('The text of the plans')
            ->and($post->fresh()->is_pinned)->toBeFalse();
    }

    // In the group, the same moderator pins the post
    $this->group->members()->attach($moderator);
    $this->actingAs($moderator->fresh())->postJson($path, ['pinned' => true])->assertOk()->assertJsonPath('data.is_pinned', true);
    expect($post->fresh()->is_pinned)->toBeTrue();
});

test('editing or deleting a post needs the access to its space, as for a comment', function (string $route) {
    $post = Post::factory()->inSpace($this->paid)->create(['author_id' => $this->insider->id, 'title' => 'Mine']);
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POST, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->paid->cuid, ApiRoutes::PARAM_POST => $post->cuid]);

    // The author lost the access group of the space
    $this->group->members()->detach($this->insider);
    $author = User::query()->findOrFail($this->insider->id);

    expect(Gate::forUser($author)->check('update', $post->fresh()))->toBeFalse()
        ->and(Gate::forUser($author)->check('delete', $post->fresh()))->toBeFalse();

    $response = $route === 'update'
        ? $this->actingAs($author)->patchJson($path, ['title' => 'Changed', 'body' => 'Changed text'])
        : $this->actingAs($author)->deleteJson($path);
    $response->assertForbidden();
    expect($post->fresh()?->title)->toBe('Mine')
        ->and($response->json('data'))->toBeNull();

    // Back in the group, the author edits and deletes the post again
    $this->group->members()->attach($this->insider);
    $author = User::query()->findOrFail($this->insider->id);
    ($route === 'update' ? $this->actingAs($author)->patchJson($path, ['title' => 'Changed']) : $this->actingAs($author)->deleteJson($path))->assertSuccessful();
})->with(['update', 'delete']);

test('a manager reorders the spaces of a group only when they can read all of them', function () {
    $this->c['community']->update(['moderator_permissions' => [...$this->c['community']->moderatorPermissions(), 'manage_spaces' => true]]);
    $moderator = $this->c['moderator']->fresh();
    $open = Space::factory()->inGroup($this->spaceGroup)->create(['type' => KnowiiSpaceType::Discussion, 'name' => 'General']);
    $this->paid->update(['name' => 'Staff room', 'position' => 5]);
    $open->update(['position' => 6]);
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUP_SPACES_REORDER, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE_GROUP => $this->spaceGroup->cuid]);

    // The whole list, the list of the spaces that the moderator reads, and a wrong list: one answer, with no space
    $answers = [];
    foreach ([[$open->cuid, $this->paid->cuid], [$open->cuid], [$open->cuid, 'aaaaaaaaaaaaaaaaaaaaaaaa']] as $cuids) {
        $response = $this->actingAs($moderator)->postJson($path, ['space_cuids' => $cuids])->assertStatus(400);
        expect($response->json('data'))->toBeNull()
            ->and($response->getContent())->not->toContain('Staff room')
            ->and($response->getContent())->not->toContain($this->paid->slug);
        $answers[] = $response->getContent();
    }
    expect(array_unique($answers))->toHaveCount(1)
        ->and($this->paid->fresh()->position)->toBe(5)
        ->and($open->fresh()->position)->toBe(6);

    // The same answer as for a wrong list in a space group that the moderator reads in full
    $other = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $first = Space::factory()->inGroup($other)->create();
    Space::factory()->inGroup($other)->create();
    $otherPath = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUP_SPACES_REORDER, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE_GROUP => $other->cuid]);
    $wrong = $this->actingAs($moderator)->postJson($otherPath, ['space_cuids' => [$first->cuid]])->assertStatus(400);
    expect($wrong->getContent())->toBe($answers[0]);

    // In the access group, the moderator reorders the spaces; an admin always does
    $this->actingAs($this->c['admin'])->postJson($path, ['space_cuids' => [$this->paid->cuid, $open->cuid]])->assertOk()->assertJsonCount(2, 'data');
    $this->group->members()->attach($moderator);
    $this->actingAs($moderator->fresh())->postJson($path, ['space_cuids' => [$open->cuid, $this->paid->cuid]])->assertOk()->assertJsonPath('data.0.cuid', $open->cuid);
});

test('a manager renames a space group only when they can read every space of it', function () {
    $this->c['community']->update(['moderator_permissions' => [...$this->c['community']->moderatorPermissions(), 'manage_spaces' => true]]);
    $moderator = $this->c['moderator']->fresh();
    $this->spaceGroup->update(['name' => 'Staff']);
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUP, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE_GROUP => $this->spaceGroup->cuid]);

    // The group holds a space that the moderator cannot read
    $this->actingAs($moderator)->patchJson($path, ['name' => 'Renamed'])->assertForbidden()->assertJsonPath('message', UpdateSpaceGroup::UNREADABLE_SPACE_MESSAGE);
    expect($this->spaceGroup->fresh()->name)->toBe('Staff');

    // A group with spaces that the moderator reads, and an empty group: the moderator renames them
    $open = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id, 'name' => 'Open']);
    Space::factory()->inGroup($open)->create();
    $empty = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id, 'name' => 'Empty']);
    foreach ([$open, $empty] as $group) {
        $this->actingAs($moderator)->patchJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUP, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE_GROUP => $group->cuid]), ['name' => 'New name'])
            ->assertOk()->assertJsonPath('data.name', 'New name');
    }

    // An admin renames the group; in the access group, the moderator does too
    $this->actingAs($this->c['admin'])->patchJson($path, ['name' => 'Team'])->assertOk()->assertJsonPath('data.name', 'Team');
    $this->group->members()->attach($moderator);
    app('auth')->forgetGuards();
    $this->actingAs($moderator->fresh())->patchJson($path, ['name' => 'Crew'])->assertOk()->assertJsonPath('data.name', 'Crew');
});

test('a manager moves a space group only when they can read every space of it', function () {
    $this->c['community']->update(['moderator_permissions' => [...$this->c['community']->moderatorPermissions(), 'manage_spaces' => true]]);
    $moderator = $this->c['moderator']->fresh();
    $this->c['community']->spaceGroups()->whereKeyNot($this->spaceGroup->id)->delete();
    $this->spaceGroup->update(['position' => 0]);
    $first = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id, 'position' => 1]);
    Space::factory()->inGroup($first)->create();
    $second = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id, 'position' => 2]);
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUPS_REORDER, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]);
    $order = fn (): array => $this->c['community']->spaceGroups()->pluck('cuid')->all();

    // The group with the space that the moderator cannot read would move: refused, nothing moves
    $this->actingAs($moderator)->postJson($path, ['space_group_cuids' => [$first->cuid, $this->spaceGroup->cuid, $second->cuid]])
        ->assertForbidden()->assertJsonPath('message', UpdateSpaceGroup::UNREADABLE_SPACE_MESSAGE);
    expect($order())->toBe([$this->spaceGroup->cuid, $first->cuid, $second->cuid]);

    // The other groups move around it
    $this->actingAs($moderator)->postJson($path, ['space_group_cuids' => [$this->spaceGroup->cuid, $second->cuid, $first->cuid]])->assertOk();
    expect($order())->toBe([$this->spaceGroup->cuid, $second->cuid, $first->cuid]);

    // An admin moves it; in the access group, the moderator does too
    $this->actingAs($this->c['admin'])->postJson($path, ['space_group_cuids' => [$second->cuid, $this->spaceGroup->cuid, $first->cuid]])->assertOk();
    $this->group->members()->attach($moderator);
    app('auth')->forgetGuards();
    $this->actingAs($moderator->fresh())->postJson($path, ['space_group_cuids' => [$this->spaceGroup->cuid, $second->cuid, $first->cuid]])->assertOk();
    expect($order())->toBe([$this->spaceGroup->cuid, $second->cuid, $first->cuid]);
});
