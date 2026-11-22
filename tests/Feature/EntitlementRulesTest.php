<?php

use App\ApiRoutes;
use App\Contracts\CommunityMembers\JoinsCommunities;
use App\Contracts\Entitlements\RefreshesEntitlements;
use App\Contracts\Entitlements\SyncsEntitlementRules;
use App\Contracts\InviteLinks\JoinsCommunitiesThroughInviteLinks;
use App\Contracts\Users\DeletesAccounts;
use App\Enums\KnowiiCommunityJoinPolicy;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Jobs\PruneUnviewableNotifications;
use App\Jobs\ReadEntitlements;
use App\Jobs\SyncCommunityEntitlementRules;
use App\Models\AccessGroup;
use App\Models\AccessGroupMember;
use App\Models\AccessGroupRule;
use App\Models\Community;
use App\Models\InviteLink;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UserIdentity;
use App\Support\AccessGroupMemberships;
use App\Support\SpaceTree;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Laravel\Jetstream\Jetstream;
use Laravel\Sanctum\Sanctum;

/*
 * Entitlement rules feed the access groups (#1386, ADR 0047): who manages the rules, the two flags of a membership (`manual`
 * and `by_rule`) in every combination, the sync (on a rule change, on a join), the unlink, the deletion of the account, the
 * export, the member list with the source of each membership and `GET /user/entitlements`. The provider is fake (see
 * tests/Pest.php).
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    entConfigure([$this->c['community']->slug]);
    $this->provider = entFakeProvider();
    $this->spaceGroup = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->group = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Paid members']);
    $this->paid = Space::factory()->inGroup($this->spaceGroup)->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $this->paid->accessGroups()->attach($this->group);
});

function entRulesPath(Community $community, AccessGroup $group): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_ACCESS_GROUP_RULES, [ApiRoutes::PARAM_COMMUNITY => $community->cuid, ApiRoutes::PARAM_ACCESS_GROUP => $group->cuid]);
}

function entMembersPath(Community $community, AccessGroup $group, ?User $member = null): string
{
    return ApiRoutes::path($member === null ? ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBERS : ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBER, array_filter([
        ApiRoutes::PARAM_COMMUNITY => $community->cuid,
        ApiRoutes::PARAM_ACCESS_GROUP => $group->cuid,
        ApiRoutes::PARAM_MEMBER => $member?->cuid,
    ]));
}

function entCanView(User $user): bool
{
    return Gate::forUser($user->fresh())->check('view', test()->paid->fresh());
}

/**
 * Give a member keys at the provider and connect their account, as a read would have left them
 *
 * @param  list<string>  $keys
 */
function entHolds(User $user, array $keys, string $subject = 'sub-ada'): UserIdentity
{
    $identity = UserIdentity::query()->where('user_id', $user->id)->where('issuer', ENT_ISSUER)->first() ?? entIdentity($user, $subject);
    foreach ($keys as $key) {
        UserEntitlement::query()->firstOrCreate(['user_id' => $user->id, 'issuer' => ENT_ISSUER, 'key' => $key]);
    }

    return $identity;
}

function entRule(AccessGroup $group, string $key): AccessGroupRule
{
    return AccessGroupRule::query()->firstOrCreate(['access_group_id' => $group->id, 'entitlement_key' => $key]);
}

function entRow(AccessGroup $group, User $user): ?AccessGroupMember
{
    return AccessGroupMember::query()->where('access_group_id', $group->id)->where('user_id', $user->id)->first();
}

function entSync(User $user): void
{
    app(SyncsEntitlementRules::class)->syncMember($user->fresh());
}

// ---------------------------------------------------------------------------------------------------------------------
// Who manages the rules
// ---------------------------------------------------------------------------------------------------------------------

test('the owner and the admins read and set the rules; moderators, members and outsiders cannot (#1386)', function () {
    $url = entRulesPath($this->c['community'], $this->group);
    $this->c['community']->update(['moderator_permissions' => [...$this->c['community']->moderatorPermissions(), 'manage_members' => true, 'manage_spaces' => true]]);

    foreach (['owner', 'admin'] as $role) {
        $this->actingAs($this->c[$role])->putJson($url, ['entitlement_keys' => ['example.paid']])->assertOk()->assertJsonPath('data.entitlement_keys', ['example.paid']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.entitlement_keys', ['example.paid']);
    }
    foreach (['moderator', 'member', 'outsider'] as $role) {
        $this->actingAs($this->c[$role])->getJson($url)->assertForbidden();
        $this->putJson($url, ['entitlement_keys' => []])->assertForbidden();
    }
    $this->app['auth']->forgetGuards();
    $this->getJson($url)->assertUnauthorized();
    $this->putJson($url, ['entitlement_keys' => []])->assertUnauthorized();
    expect(AccessGroupRule::query()->pluck('entitlement_key')->all())->toBe(['example.paid']);
});

test('the rules of a group of another community are not reachable through this one (#1386)', function () {
    $other = communityWithMembers();
    entConfigure([$this->c['community']->slug, $other['community']->slug]);
    $foreign = AccessGroup::query()->create(['community_id' => $other['community']->id, 'name' => 'Foreign']);

    $this->actingAs($this->c['owner'])->putJson(entRulesPath($this->c['community'], $foreign), ['entitlement_keys' => ['example.paid']])->assertNotFound();
    $this->getJson(entRulesPath($this->c['community'], $foreign))->assertNotFound();
});

test('a community that the operator does not list has no rules: 404 for its owner, and nothing is stored (#1386)', function () {
    entConfigure(['another-community']);
    $url = entRulesPath($this->c['community'], $this->group);

    $this->actingAs($this->c['owner'])->getJson($url)->assertNotFound();
    $this->putJson($url, ['entitlement_keys' => ['example.paid']])->assertNotFound();
    expect(AccessGroupRule::query()->count())->toBe(0);

    $this->actingAs($this->c['owner']->fresh())->get('/community/'.$this->c['community']->slug.'/settings')->assertInertia(fn ($page) => $page->where('permissions.canManageEntitlementRules', false));
    $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_ACCESS_GROUPS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]))
        ->assertOk()->assertJsonMissingPath('data.0.entitlement_keys');
});

test('an empty list of listed communities means none (#1386)', function () {
    entConfigure([]);

    $this->actingAs($this->c['owner'])->putJson(entRulesPath($this->c['community'], $this->group), ['entitlement_keys' => ['example.paid']])->assertNotFound();
});

test('the settings page tells owner and admins of a listed community that they can give a group by keys (#1386)', function () {
    foreach (['owner', 'admin'] as $role) {
        $this->actingAs($this->c[$role]->fresh())->get('/community/'.$this->c['community']->slug.'/settings')
            ->assertInertia(fn ($page) => $page->where('permissions.canManageEntitlementRules', true));
    }

    // A moderator who manages the members opens the page, and cannot manage the rules
    $this->c['community']->update(['moderator_permissions' => [...$this->c['community']->moderatorPermissions(), 'manage_members' => true]]);
    $this->actingAs($this->c['moderator']->fresh())->get('/community/'.$this->c['community']->slug.'/settings')
        ->assertInertia(fn ($page) => $page->where('permissions.canManageEntitlementRules', false));
});

test('the keys are free text checked against a safe pattern (#1386)', function (mixed $input) {
    $this->actingAs($this->c['owner'])->putJson(entRulesPath($this->c['community'], $this->group), $input)->assertStatus(400);

    expect(AccessGroupRule::query()->count())->toBe(0);
})->with([
    'no list' => [[]],
    'not a list' => [['entitlement_keys' => 'example.paid']],
    'a key that is not text' => [['entitlement_keys' => [12]]],
    'an array as a key' => [['entitlement_keys' => [['a']]]],
    'an empty key' => [['entitlement_keys' => ['']]],
    'a space' => [['entitlement_keys' => ['two words']]],
    'a slash' => [['entitlement_keys' => ['a/b']]],
    'a colon' => [['entitlement_keys' => ['a:b']]],
    'a wildcard' => [['entitlement_keys' => ['a.*']]],
    'a line break' => [['entitlement_keys' => ["a\nb"]]],
    'markup' => [['entitlement_keys' => ['<b>x</b>']]],
    'an accent' => [['entitlement_keys' => ['café']]],
    'a key of 101 characters' => [['entitlement_keys' => [str_repeat('a', 101)]]],
    'the same key twice' => [['entitlement_keys' => ['a', 'a']]],
    'more than 20 keys' => [['entitlement_keys' => array_map(fn (int $i) => 'key.'.$i, range(1, 21))]],
]);

test('keys with letters, digits, dot, dash and underscore up to 100 characters, and 20 keys, are accepted (#1386)', function () {
    $keys = [str_repeat('A', 100), 'a.b-c_d9', ...array_map(fn (int $i) => 'key.'.$i, range(1, 18))];

    $this->actingAs($this->c['owner'])->putJson(entRulesPath($this->c['community'], $this->group), ['entitlement_keys' => $keys])->assertOk();

    expect(AccessGroupRule::query()->count())->toBe(20);
});

test('a key is stored as it is: the platform never interprets it (#1386)', function () {
    $this->actingAs($this->c['owner'])->putJson(entRulesPath($this->c['community'], $this->group), ['entitlement_keys' => ['Some.Odd_Key-1']])->assertOk();

    expect(AccessGroupRule::query()->first()?->entitlement_key)->toBe('Some.Odd_Key-1');
});

test('nothing in the code names a key of a provider (#1386)', function () {
    $names = ['knowii.free', 'knowii.master', 'foundation.box', 'skills.standalone', 'kwk'];
    $files = [];
    foreach (['app', 'config', 'routes', 'database'] as $directory) {
        foreach (File::allFiles(base_path($directory)) as $file) {
            $files[] = $file->getPathname();
        }
    }
    $found = [];
    foreach ($files as $path) {
        $source = (string) file_get_contents($path);
        foreach ($names as $name) {
            if (str_contains($source, "'".$name."'") || str_contains($source, '"'.$name.'"')) {
                $found[] = $path.' '.$name;
            }
        }
    }

    expect($found)->toBe([]);
});

test('the rules route replaces the whole list, sorted, and an empty list removes every rule (#1386)', function () {
    $url = entRulesPath($this->c['community'], $this->group);
    $this->actingAs($this->c['owner']);

    $this->putJson($url, ['entitlement_keys' => ['b.key', 'a.key']])->assertOk()->assertJsonPath('data.entitlement_keys', ['a.key', 'b.key']);
    $this->putJson($url, ['entitlement_keys' => ['b.key', 'c.key']])->assertOk()->assertJsonPath('data.entitlement_keys', ['b.key', 'c.key']);
    expect(AccessGroupRule::query()->orderBy('entitlement_key')->pluck('entitlement_key')->all())->toBe(['b.key', 'c.key']);

    $this->putJson($url, ['entitlement_keys' => []])->assertOk()->assertJsonPath('data.entitlement_keys', []);
    expect(AccessGroupRule::query()->count())->toBe(0);
});

test('the same key can give two groups, and a rule is unique per group and key (#1386)', function () {
    $other = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Other']);
    $this->actingAs($this->c['owner']);
    $this->putJson(entRulesPath($this->c['community'], $this->group), ['entitlement_keys' => ['example.paid']])->assertOk();
    $this->putJson(entRulesPath($this->c['community'], $other), ['entitlement_keys' => ['example.paid']])->assertOk();

    expect(AccessGroupRule::query()->count())->toBe(2)
        ->and(fn () => AccessGroupRule::query()->create(['access_group_id' => $this->group->id, 'entitlement_key' => 'example.paid']))->toThrow(QueryException::class);
});

test('the rules go with their group (#1386)', function () {
    entRule($this->group, 'example.paid');

    $this->actingAs($this->c['owner'])->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY_ACCESS_GROUP, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_ACCESS_GROUP => $this->group->cuid]))->assertNoContent();

    expect(AccessGroupRule::query()->count())->toBe(0);
});

test('the keys of a group show in the list and in the answers of create and update, for owner and admins only (#1386)', function () {
    entRule($this->group, 'example.paid');
    $list = ApiRoutes::path(ApiRoutes::COMMUNITY_ACCESS_GROUPS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]);
    $this->c['community']->update(['moderator_permissions' => [...$this->c['community']->moderatorPermissions(), 'manage_members' => true]]);

    $this->actingAs($this->c['owner'])->getJson($list)->assertOk()->assertJsonPath('data.0.entitlement_keys', ['example.paid']);
    $this->actingAs($this->c['admin'])->getJson($list)->assertOk()->assertJsonPath('data.0.entitlement_keys', ['example.paid']);
    $this->actingAs($this->c['owner'])->postJson($list, ['name' => 'New'])->assertCreated()->assertJsonPath('data.entitlement_keys', []);
    $this->patchJson(ApiRoutes::path(ApiRoutes::COMMUNITY_ACCESS_GROUP, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_ACCESS_GROUP => $this->group->cuid]), ['description' => 'x'])
        ->assertOk()->assertJsonPath('data.entitlement_keys', ['example.paid']);

    // A moderator who manages members reads the groups, not the keys
    $this->actingAs($this->c['moderator'])->getJson($list)->assertOk()->assertJsonMissingPath('data.0.entitlement_keys');
});

// ---------------------------------------------------------------------------------------------------------------------
// The API abilities
// ---------------------------------------------------------------------------------------------------------------------

test('the rules routes need the abilities groups:read and groups:write (#1386)', function () {
    $url = entRulesPath($this->c['community'], $this->group);

    Sanctum::actingAs($this->c['owner'], ['groups:read']);
    $this->getJson($url)->assertOk();
    $this->putJson($url, ['entitlement_keys' => ['example.paid']])->assertForbidden();

    Sanctum::actingAs($this->c['owner'], ['groups:write']);
    $this->putJson($url, ['entitlement_keys' => ['example.paid']])->assertOk();
    $this->getJson($url)->assertForbidden();

    Sanctum::actingAs($this->c['owner'], ['communities:read', 'communities:write', 'posts:write']);
    $this->getJson($url)->assertForbidden();
    $this->putJson($url, ['entitlement_keys' => []])->assertForbidden();

    // The right of the member still applies: a token cannot give more than the member has
    Sanctum::actingAs($this->c['member'], ['groups:read', 'groups:write']);
    $this->getJson($url)->assertForbidden();
    $this->putJson($url, ['entitlement_keys' => []])->assertForbidden();
});

test('GET /user/entitlements needs the ability entitlements:read, which is not in the default set (#1386)', function () {
    entHolds($this->c['member'], ['example.paid']);

    Sanctum::actingAs($this->c['member'], ['user:read', 'communities:read']);
    $this->getJson('/api/v1/user/entitlements')->assertForbidden();

    Sanctum::actingAs($this->c['member'], ['entitlements:read']);
    $this->getJson('/api/v1/user/entitlements')->assertOk();

    expect(Jetstream::$permissions)->toContain('entitlements:read')
        ->and(Jetstream::$defaultPermissions)->not->toContain('entitlements:read');
});

// ---------------------------------------------------------------------------------------------------------------------
// The flags of a membership
// ---------------------------------------------------------------------------------------------------------------------

test('a membership that exists already was added by hand: the rows of before the change get manual (#1386)', function () {
    $this->c['member']->accessGroups()->attach($this->group);

    $row = entRow($this->group, $this->c['member']);

    expect($row?->manual)->toBeTrue()->and($row?->by_rule)->toBeFalse();
});

test('a row exists while one of the flags is true: the database refuses a row with none (#1386)', function () {
    expect(fn () => AccessGroupMember::query()->create(['access_group_id' => $this->group->id, 'user_id' => $this->c['member']->id, 'manual' => false, 'by_rule' => false]))
        ->toThrow(QueryException::class);
});

test('manual only: added by hand through the API, it stays when the member holds no key (#1386)', function () {
    entRule($this->group, 'example.paid');

    $this->actingAs($this->c['owner'])->postJson(entMembersPath($this->c['community'], $this->group), ['member' => $this->c['member']->cuid])->assertOk();
    entSync($this->c['member']);

    $row = entRow($this->group, $this->c['member']);
    expect($row?->manual)->toBeTrue()->and($row?->by_rule)->toBeFalse()
        ->and(AccessGroupMember::query()->where('user_id', $this->c['member']->id)->count())->toBe(1)
        ->and(entCanView($this->c['member']))->toBeTrue();
});

test('rule only: the member holds the key and has no row from a hand add (#1386)', function () {
    entRule($this->group, 'example.paid');
    entHolds($this->c['member'], ['example.paid']);

    entSync($this->c['member']);

    $row = entRow($this->group, $this->c['member']);
    expect($row?->manual)->toBeFalse()->and($row?->by_rule)->toBeTrue()
        ->and(entCanView($this->c['member']))->toBeTrue();
});

test('both: a rule after a hand add, and a hand add after a rule, give one row with both flags (#1386)', function () {
    entRule($this->group, 'example.paid');
    entHolds($this->c['member'], ['example.paid']);
    entHolds($this->c['moderator'], ['example.paid'], 'sub-grace');

    // The hand add first, then the rule
    $this->actingAs($this->c['owner'])->postJson(entMembersPath($this->c['community'], $this->group), ['member' => $this->c['member']->cuid])->assertOk();
    entSync($this->c['member']);
    // The rule first, then the hand add (twice: adding again changes nothing)
    entSync($this->c['moderator']);
    $this->postJson(entMembersPath($this->c['community'], $this->group), ['member' => $this->c['moderator']->cuid])->assertOk();
    $this->postJson(entMembersPath($this->c['community'], $this->group), ['member' => $this->c['moderator']->cuid])->assertOk();

    foreach (['member', 'moderator'] as $role) {
        $row = entRow($this->group, $this->c[$role]);
        expect($row?->manual)->toBeTrue()->and($row?->by_rule)->toBeTrue()
            ->and(AccessGroupMember::query()->where('user_id', $this->c[$role]->id)->count())->toBe(1);
    }
});

test('a manual removal while a rule holds clears manual only: the member stays, and says so on the list (#1386)', function () {
    entRule($this->group, 'example.paid');
    entHolds($this->c['member'], ['example.paid']);
    $this->actingAs($this->c['owner'])->postJson(entMembersPath($this->c['community'], $this->group), ['member' => $this->c['member']->cuid])->assertOk();
    entSync($this->c['member']);

    $this->deleteJson(entMembersPath($this->c['community'], $this->group, $this->c['member']))->assertNoContent();

    $row = entRow($this->group, $this->c['member']);
    expect($row?->manual)->toBeFalse()->and($row?->by_rule)->toBeTrue()
        ->and(entCanView($this->c['member']))->toBeTrue();
    $this->getJson(entMembersPath($this->c['community'], $this->group))->assertOk()->assertJsonPath('data.0.sources', ['by_rule']);

    // Removing again by hand changes nothing and is not an error
    $this->deleteJson(entMembersPath($this->c['community'], $this->group, $this->c['member']))->assertNoContent();
    expect(entRow($this->group, $this->c['member'])?->by_rule)->toBeTrue();
});

test('a manual removal without a rule deletes the row, and a person who is not in the group gives a 404 (#1386)', function () {
    $this->actingAs($this->c['owner'])->postJson(entMembersPath($this->c['community'], $this->group), ['member' => $this->c['member']->cuid])->assertOk();

    $this->deleteJson(entMembersPath($this->c['community'], $this->group, $this->c['member']))->assertNoContent();
    expect(entRow($this->group, $this->c['member']))->toBeNull()->and(entCanView($this->c['member']))->toBeFalse();

    $this->deleteJson(entMembersPath($this->c['community'], $this->group, $this->c['member']))->assertNotFound();
});

test('a rule lost while the member was added by hand keeps the row with manual only (#1386)', function () {
    entRule($this->group, 'example.paid');
    entHolds($this->c['member'], ['example.paid']);
    $this->actingAs($this->c['owner'])->postJson(entMembersPath($this->c['community'], $this->group), ['member' => $this->c['member']->cuid])->assertOk();
    entSync($this->c['member']);

    UserEntitlement::query()->delete();
    entSync($this->c['member']);

    $row = entRow($this->group, $this->c['member']);
    expect($row?->manual)->toBeTrue()->and($row?->by_rule)->toBeFalse()->and(entCanView($this->c['member']))->toBeTrue();
});

test('a rule lost without a hand add deletes the row, and the member loses the space (#1386)', function () {
    entRule($this->group, 'example.paid');
    entHolds($this->c['member'], ['example.paid']);
    entSync($this->c['member']);
    expect(entCanView($this->c['member']))->toBeTrue();

    UserEntitlement::query()->delete();
    entSync($this->c['member']);

    expect(entRow($this->group, $this->c['member']))->toBeNull()->and(entCanView($this->c['member']))->toBeFalse();
});

test('the reading rule and the counts do not change: a member by rule counts as a member and sees the space, the navigation follows (#1386)', function () {
    entRule($this->group, 'example.paid');
    entHolds($this->c['member'], ['example.paid']);
    entSync($this->c['member']);

    $names = fn (User $user) => SpaceTree::for($this->c['community'], $user->fresh())->flatMap(fn ($group) => $group->spaces->pluck('cuid'))->all();
    expect($names($this->c['member']))->toContain($this->paid->cuid)
        ->and($names($this->c['moderator']))->not->toContain($this->paid->cuid);
    $this->actingAs($this->c['owner'])->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_ACCESS_GROUPS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]))
        ->assertOk()->assertJsonPath('data.0.members_count', 1);
});

// ---------------------------------------------------------------------------------------------------------------------
// The sync
// ---------------------------------------------------------------------------------------------------------------------

test('the groups of a member are the groups with a rule whose key the member holds, any of several keys (#1386)', function () {
    $second = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Courses']);
    $third = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Untouched']);
    entRule($this->group, 'example.paid');
    entRule($this->group, 'example.plus');
    entRule($second, 'example.course');
    entRule($third, 'example.other');
    entHolds($this->c['member'], ['example.plus', 'example.course']);

    entSync($this->c['member']);

    expect(entRow($this->group, $this->c['member'])?->by_rule)->toBeTrue()
        ->and(entRow($second, $this->c['member'])?->by_rule)->toBeTrue()
        ->and(entRow($third, $this->c['member']))->toBeNull();
});

test('keys are compared exactly, and only the keys of the issuer of this instance count (#1386)', function () {
    entRule($this->group, 'example.paid');
    entIdentity($this->c['member']);
    UserEntitlement::query()->create(['user_id' => $this->c['member']->id, 'issuer' => ENT_ISSUER, 'key' => 'Example.Paid']);
    UserEntitlement::query()->create(['user_id' => $this->c['member']->id, 'issuer' => 'https://other.example.test', 'key' => 'example.paid']);

    entSync($this->c['member']);

    expect(entRow($this->group, $this->c['member']))->toBeNull();
});

test('only a member of the community gets a rule membership: not an outsider, not the owner who is not in the member list (#1386)', function () {
    entRule($this->group, 'example.paid');
    entHolds($this->c['outsider'], ['example.paid'], 'sub-outsider');
    entHolds($this->c['owner'], ['example.paid'], 'sub-owner');

    entSync($this->c['outsider']);
    entSync($this->c['owner']);

    expect(AccessGroupMember::query()->count())->toBe(0);
});

test('a community that is not listed is never touched by the sync (#1386)', function () {
    $other = communityWithMembers();
    $foreign = AccessGroup::query()->create(['community_id' => $other['community']->id, 'name' => 'Foreign']);
    entRule($foreign, 'example.paid');
    $other['community']->users()->attach($this->c['member'], ['role' => 'member']);
    entHolds($this->c['member'], ['example.paid']);

    entSync($this->c['member']);

    expect(entRow($foreign, $this->c['member']))->toBeNull();
});

test('AccessGroupsChanged goes out once per community that changed, and the prune of the notifications follows (#1386)', function () {
    $second = communityWithMembers();
    entConfigure([$this->c['community']->slug, $second['community']->slug]);
    $secondGroup = AccessGroup::query()->create(['community_id' => $second['community']->id, 'name' => 'Paid']);
    $second['community']->users()->attach($this->c['member'], ['role' => 'member']);
    $extra = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Extra']);
    entRule($this->group, 'example.paid');
    entRule($extra, 'example.paid');
    entRule($secondGroup, 'example.paid');
    entHolds($this->c['member'], ['example.paid']);

    Event::fake([AccessGroupsChanged::class]);
    entSync($this->c['member']);
    Event::assertDispatchedTimes(AccessGroupsChanged::class, 2);
    Event::assertDispatched(AccessGroupsChanged::class, fn (AccessGroupsChanged $event) => $event->communityCuid === $this->c['community']->cuid);
    Event::assertDispatched(AccessGroupsChanged::class, fn (AccessGroupsChanged $event) => $event->communityCuid === $second['community']->cuid);

    // Nothing changed: nothing is sent
    Event::fake([AccessGroupsChanged::class]);
    entSync($this->c['member']);
    Event::assertNotDispatched(AccessGroupsChanged::class);
});

test('a member who loses the group loses the notifications of its spaces through the existing prune (#1386, ADR 0042)', function () {
    entRule($this->group, 'example.paid');
    entHolds($this->c['member'], ['example.paid']);
    entSync($this->c['member']);

    Queue::fake();
    UserEntitlement::query()->delete();
    entSync($this->c['member']);

    Queue::assertPushed(PruneUnviewableNotifications::class, fn (PruneUnviewableNotifications $job) => $job->community->is($this->c['community']));
});

test('a new, changed or removed rule runs the sync for the members of the community in a queued job (#1386)', function () {
    $this->actingAs($this->c['owner']);
    $url = entRulesPath($this->c['community'], $this->group);
    entHolds($this->c['member'], ['example.paid']);
    entHolds($this->c['moderator'], ['example.plus'], 'sub-grace');
    entHolds($this->c['outsider'], ['example.paid'], 'sub-out');

    // A new rule
    $this->putJson($url, ['entitlement_keys' => ['example.paid']])->assertOk();
    expect(entRow($this->group, $this->c['member'])?->by_rule)->toBeTrue()
        ->and(entRow($this->group, $this->c['moderator']))->toBeNull()
        ->and(entRow($this->group, $this->c['outsider']))->toBeNull();

    // A changed rule
    $this->putJson($url, ['entitlement_keys' => ['example.plus']])->assertOk();
    expect(entRow($this->group, $this->c['member']))->toBeNull()
        ->and(entRow($this->group, $this->c['moderator'])?->by_rule)->toBeTrue();

    // A removed rule
    $this->putJson($url, ['entitlement_keys' => []])->assertOk();
    expect(AccessGroupMember::query()->count())->toBe(0);
});

test('the rule change is queued, after the commit, once, and not when nothing changed (#1386)', function () {
    Queue::fake();
    $this->actingAs($this->c['owner']);
    $url = entRulesPath($this->c['community'], $this->group);

    $this->putJson($url, ['entitlement_keys' => ['example.paid']])->assertOk();
    Queue::assertPushed(SyncCommunityEntitlementRules::class, 1);
    Queue::assertPushed(SyncCommunityEntitlementRules::class, fn (SyncCommunityEntitlementRules $job) => $job->community->is($this->c['community']));

    $this->putJson($url, ['entitlement_keys' => ['example.paid']])->assertOk();
    Queue::assertPushed(SyncCommunityEntitlementRules::class, 1);
});

test('a rule change keeps the memberships that were added by hand, and works for many members (#1386)', function () {
    $hand = $this->c['moderator'];
    AccessGroupMemberships::addManual($this->group, $hand);
    $members = [];
    foreach (range(1, 25) as $i) {
        $user = User::factory()->withUserProfile()->create();
        $this->c['community']->users()->attach($user, ['role' => 'member']);
        entHolds($user, ['example.paid'], 'sub-many-'.$i);
        $members[] = $user;
    }

    $this->actingAs($this->c['owner'])->putJson(entRulesPath($this->c['community'], $this->group), ['entitlement_keys' => ['example.paid']])->assertOk();
    expect(AccessGroupMember::query()->where('by_rule', true)->count())->toBe(25);

    $this->putJson(entRulesPath($this->c['community'], $this->group), ['entitlement_keys' => []])->assertOk();
    expect(AccessGroupMember::query()->count())->toBe(1)->and(entRow($this->group, $hand)?->manual)->toBeTrue();
});

test('a person who joins by themselves a community with rules gets the groups of their entitlements (#1386)', function () {
    $this->c['community']->update(['join_policy' => KnowiiCommunityJoinPolicy::Open]);
    entRule($this->group, 'example.paid');
    entGrant($this->provider, 'sub-out', ['example.paid']);
    entIdentity($this->c['outsider'], 'sub-out');

    app(JoinsCommunities::class)->join($this->c['outsider'], $this->c['community']);

    $row = entRow($this->group, $this->c['outsider']);
    expect($row?->by_rule)->toBeTrue()->and($row?->manual)->toBeFalse()
        ->and($this->provider->reads)->toHaveCount(1)
        ->and(entCanView($this->c['outsider']))->toBeTrue();
});

test('a person who joins through an invite link a community with rules gets the groups of their entitlements, and the link never gives a group that a rule feeds (#1391)', function () {
    entRule($this->group, 'example.paid');
    entGrant($this->provider, 'sub-out', ['example.paid']);
    entIdentity($this->c['outsider'], 'sub-out');
    $link = InviteLink::factory()->create(['community_id' => $this->c['community']->id]);
    // Saved on the link before the rule existed: the join does not give it by hand
    DB::table('invite_link_access_groups')->insert(['invite_link_id' => $link->id, 'access_group_id' => $this->group->id]);

    app(JoinsCommunitiesThroughInviteLinks::class)->join($this->c['outsider'], $link->token);

    $row = entRow($this->group, $this->c['outsider']);
    expect($row?->by_rule)->toBeTrue()->and($row?->manual)->toBeFalse()
        ->and($this->provider->reads)->toHaveCount(1);
});

test('a person who accepts an invitation to a community with rules gets the groups of their entitlements (#1386)', function () {
    entRule($this->group, 'example.paid');
    entGrant($this->provider, 'sub-out', ['example.paid']);
    entIdentity($this->c['outsider'], 'sub-out');
    $invitation = $this->c['community']->communityInvitations()->create(['email' => $this->c['outsider']->email, 'role' => KnowiiCommunityMemberRole::Member]);

    $this->actingAs($this->c['outsider'])->get(URL::temporarySignedRoute('community-invitations.accept', $invitation->expiresAt(), ['invitation' => $invitation]))->assertRedirect();

    expect(entRow($this->group, $this->c['outsider'])?->by_rule)->toBeTrue();
});

test('joining reads nothing when the community has no rules, the person has no connected account, or the community is not listed (#1386)', function (string $case) {
    $this->c['community']->update(['join_policy' => KnowiiCommunityJoinPolicy::Open]);
    entGrant($this->provider, 'sub-out', ['example.paid']);
    match ($case) {
        'no rules' => entIdentity($this->c['outsider'], 'sub-out'),
        'no account' => entRule($this->group, 'example.paid'),
        'not listed' => (function () {
            entRule($this->group, 'example.paid');
            entIdentity($this->c['outsider'], 'sub-out');
            entConfigure(['another-community']);
        })(),
    };
    Queue::fake();

    app(JoinsCommunities::class)->join($this->c['outsider'], $this->c['community']);

    Queue::assertNotPushed(ReadEntitlements::class);
    expect($this->provider->reads)->toBe([])->and(AccessGroupMember::query()->count())->toBe(0);
})->with(['no rules', 'no account', 'not listed']);

test('joining without the entitlements feature changes nothing (#1386)', function () {
    config(['services.oidc.entitlements_url' => null]);
    $this->c['community']->update(['join_policy' => KnowiiCommunityJoinPolicy::Open]);
    entRule($this->group, 'example.paid');
    entIdentity($this->c['outsider'], 'sub-out');
    Queue::fake();

    app(JoinsCommunities::class)->join($this->c['outsider'], $this->c['community']);

    Queue::assertNotPushed(ReadEntitlements::class);
    expect($this->provider->reads)->toBe([]);
});

test('a former member who leaves, or is removed, keeps no rule membership, and gets it back when they join again (#1386)', function () {
    entRule($this->group, 'example.paid');
    entHolds($this->c['member'], ['example.paid']);
    entSync($this->c['member']);
    expect(entRow($this->group, $this->c['member']))->not->toBeNull();

    $this->actingAs($this->c['member'])->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERSHIP, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]))->assertNoContent();
    expect(entRow($this->group, $this->c['member']))->toBeNull();

    // Syncing a person who is not a member any more gives nothing
    entSync($this->c['member']);
    expect(entRow($this->group, $this->c['member']))->toBeNull();
});

// ---------------------------------------------------------------------------------------------------------------------
// The unlink and the deletion of the account
// ---------------------------------------------------------------------------------------------------------------------

test('unlinking the account deletes the stored keys and the rule memberships at once, and keeps the hand adds (#1386)', function () {
    $other = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Hand']);
    entRule($this->group, 'example.paid');
    $identity = entHolds($this->c['member'], ['example.paid', 'example.other']);
    AccessGroupMemberships::addManual($other, $this->c['member']);
    entSync($this->c['member']);
    expect(entCanView($this->c['member']))->toBeTrue();

    $this->actingAs($this->c['member'])->deleteJson(ApiRoutes::path(ApiRoutes::USER_IDENTITY, [ApiRoutes::PARAM_IDENTITY_CUID => $identity->cuid]))->assertNoContent();

    expect(UserEntitlement::query()->count())->toBe(0)
        ->and(entRow($this->group, $this->c['member']))->toBeNull()
        ->and(entCanView($this->c['member']))->toBeFalse()
        ->and(entRow($other, $this->c['member'])?->manual)->toBeTrue();
});

test('unlinking an account of another issuer keeps the rule memberships of this one (#1386)', function () {
    entRule($this->group, 'example.paid');
    entHolds($this->c['member'], ['example.paid']);
    entSync($this->c['member']);
    $old = UserIdentity::factory()->create(['user_id' => $this->c['member']->id, 'issuer' => 'https://old.example.test', 'subject' => 'sub-old']);
    UserEntitlement::query()->create(['user_id' => $this->c['member']->id, 'issuer' => 'https://old.example.test', 'key' => 'old.key']);

    $this->actingAs($this->c['member'])->deleteJson(ApiRoutes::path(ApiRoutes::USER_IDENTITY, [ApiRoutes::PARAM_IDENTITY_CUID => $old->cuid]))->assertNoContent();

    expect(entStoredKeysOf($this->c['member']))->toBe(['example.paid'])
        ->and(entRow($this->group, $this->c['member'])?->by_rule)->toBeTrue();
});

function entStoredKeysOf(User $user): array
{
    return UserEntitlement::query()->where('user_id', $user->id)->orderBy('key')->pluck('key')->all();
}

test('deleting the account deletes the stored keys and the memberships at once (#1386)', function () {
    entRule($this->group, 'example.paid');
    entHolds($this->c['member'], ['example.paid']);
    entSync($this->c['member']);
    AccessGroupMemberships::addManual(AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Hand']), $this->c['member']);

    app(DeletesAccounts::class)->delete($this->c['member']);

    expect(UserEntitlement::query()->count())->toBe(0)
        ->and(AccessGroupMember::query()->count())->toBe(0)
        ->and(UserIdentity::query()->count())->toBe(0);
});

test('a read that finds the subject gone leaves no key and no rule membership behind (#1386)', function () {
    entRule($this->group, 'example.paid');
    $identity = entHolds($this->c['member'], ['example.paid']);
    entSync($this->c['member']);

    app(RefreshesEntitlements::class)->refresh($identity);

    expect(UserEntitlement::query()->count())->toBe(0)->and(entRow($this->group, $this->c['member']))->toBeNull();
});

// ---------------------------------------------------------------------------------------------------------------------
// The export, the member list, GET /user/entitlements, the profile
// ---------------------------------------------------------------------------------------------------------------------

test('the member export holds the keys, the date of the check and where each membership comes from (#1386)', function () {
    entRule($this->group, 'example.paid');
    $identity = entHolds($this->c['member'], ['example.paid', 'example.other']);
    $identity->forceFill(['entitlements_checked_at' => now()])->save();
    $hand = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Hand']);
    AccessGroupMemberships::addManual($hand, $this->c['member']);
    entSync($this->c['member']);
    entHolds($this->c['moderator'], ['somebody.elses.key'], 'sub-grace');

    expect(Artisan::call('users:export', ['email' => $this->c['member']->email]))->toBe(0);
    $path = trim(Artisan::output());
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $account = json_decode((string) $zip->getFromName('account.json'), true);
    $memberships = json_decode((string) $zip->getFromName('memberships.json'), true);
    $readme = (string) $zip->getFromName('README.txt');
    $zip->close();
    File::delete($path);

    expect(array_column($account['entitlements'], 'key'))->toBe(['example.other', 'example.paid'])
        ->and($account['entitlements'][0]['issuer'])->toBe(ENT_ISSUER)
        ->and(array_keys($account['entitlements'][0]))->toBe(['issuer', 'key'])
        ->and($account['connected_accounts'][0]['entitlements_checked_at'])->not->toBeNull()
        ->and(json_encode($account))->not->toContain('somebody.elses.key')
        ->and($readme)->toContain('entitlement keys');
    $groups = collect($memberships['access_groups'])->keyBy('name');
    expect($groups['Paid members']['added_by_hand'])->toBeFalse()->and($groups['Paid members']['given_by_rule'])->toBeTrue()
        ->and($groups['Hand']['added_by_hand'])->toBeTrue()->and($groups['Hand']['given_by_rule'])->toBeFalse();
});

test('the community export holds the keys of the rules, no member data of the rules (#1386)', function () {
    entRule($this->group, 'example.paid');

    expect(Artisan::call('communities:export', ['slug' => $this->c['community']->slug]))->toBe(0);
    $path = trim(Artisan::output());
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $community = json_decode((string) $zip->getFromName('community.json'), true);
    $zip->close();
    File::delete($path);

    expect(collect($community['access_groups'])->firstWhere('name', 'Paid members')['entitlement_keys'])->toBe(['example.paid']);
});

test('the member list of a group returns the source of each membership, never a key (#1386)', function () {
    entRule($this->group, 'example.paid');
    entHolds($this->c['member'], ['example.paid']);
    entSync($this->c['member']);
    AccessGroupMemberships::addManual($this->group, $this->c['moderator']);
    AccessGroupMemberships::addManual($this->group, $this->c['admin']);
    entHolds($this->c['admin'], ['example.paid'], 'sub-admin');
    entSync($this->c['admin']);

    $response = $this->actingAs($this->c['owner'])->getJson(entMembersPath($this->c['community'], $this->group))->assertOk();

    $sources = collect($response->json('data'))->mapWithKeys(fn (array $row) => [$row['cuid'] => $row['sources']]);
    expect($sources[$this->c['member']->cuid])->toBe(['by_rule'])
        ->and($sources[$this->c['moderator']->cuid])->toBe(['manual'])
        ->and($sources[$this->c['admin']->cuid])->toBe(['manual', 'by_rule'])
        ->and($response->json('metadata.pagination.totalCount'))->toBe(3)
        ->and($response->getContent())->not->toContain('example.paid');
});

test('GET /user/entitlements returns the own keys and the date of the last check, and nothing of other members (#1386)', function () {
    $identity = entHolds($this->c['member'], ['b.key', 'a.key']);
    $identity->forceFill(['entitlements_checked_at' => now()])->save();
    entHolds($this->c['moderator'], ['not.mine'], 'sub-grace');

    $response = $this->actingAs($this->c['member'])->getJson('/api/v1/user/entitlements')->assertOk();

    expect($response->json('data.entitlements'))->toBe(['a.key', 'b.key'])
        ->and($response->json('data.checked_at'))->not->toBeNull()
        ->and($response->getContent())->not->toContain('not.mine')->not->toContain('sub-ada');
});

test('GET /user/entitlements is empty for a member without a connected account, and needs a signed-in member (#1386)', function () {
    $this->actingAs($this->c['member'])->getJson('/api/v1/user/entitlements')->assertOk()->assertJsonPath('data.entitlements', [])->assertJsonPath('data.checked_at', null);

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/user/entitlements')->assertUnauthorized();
});

test('the profile settings show what the account gives access to when the feature is on and the account is connected (#1386)', function () {
    $this->actingAs($this->c['member']->fresh())->get('/user/profile')->assertInertia(fn ($page) => $page->missing('entitlements'));

    $identity = entHolds($this->c['member'], ['b.key', 'a.key']);
    $identity->forceFill(['entitlements_checked_at' => now()])->save();

    $this->get('/user/profile')->assertInertia(fn ($page) => $page
        ->where('entitlements.keys', ['a.key', 'b.key'])
        ->whereType('entitlements.checked_at', 'string'));
});
