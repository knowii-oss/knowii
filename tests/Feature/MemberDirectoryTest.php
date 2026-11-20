<?php

use App\ApiRoutes;
use App\Constants;
use App\Contracts\CommunityMembers\LeavesCommunities;
use App\Contracts\CommunityMembers\RemovesCommunityMembers;
use App\Enums\KnowiiCommunityMemberRole;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use App\Support\CommunityMemberQuery;
use App\Support\ProfileFieldVisibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Visus\Cuid2\Cuid2;

/*
 * The member directory (#148, ADR 0056): the page "Members" of a community, for its members only, and the API list
 * that it reads. An entry holds seven fields; the headline is its only profile field (#1688, ADR 0020). The search reads
 * the name and the username only, and never leaves the community. Three sorts: `role` (the API without `sort`),
 * `name` (the page without `sort`) and `newest`.
 */

const DIRECTORY_COMPONENT = 'Members/Index';

// WARNING: aligned with MemberWithRoleResource.php
const DIRECTORY_ENTRY_KEYS = ['avatar_url', 'cuid', 'headline', 'member_since', 'name', 'role', 'username'];

// Texts that no page holds by accident, with no character that JSON or HTML writes in another way
const DIRECTORY_EMAIL_PROBE = 'directory-email-probe-q7x2@example.com';
const DIRECTORY_HEADLINE_PROBE = 'DIRECTORY-HEADLINE-PROBE grows tomatoes';
const DIRECTORY_BIO_PROBE = 'DIRECTORY-BIO-PROBE qxbiography';
const DIRECTORY_LOCATION_PROBE = 'DIRECTORY-LOCATION-PROBE';
const DIRECTORY_PHONE_PROBE = '+32 000 98 76 54';
const DIRECTORY_LINK_PROBE = 'DIRECTORY-LINK-PROBE';

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    // A second plain member: the one who reads the entry of the first one
    $this->reader = User::factory()->withUserProfile()->create();
    $this->community->users()->attach($this->reader, ['role' => KnowiiCommunityMemberRole::Member]);
});

/**
 * @param  array<string, string|int>  $query
 */
function directoryPageUrl(Community $community, array $query = []): string
{
    return route('members.index', ['communitySlug' => $community->slug, ...$query]);
}

/**
 * @param  array<string, string|int>  $query
 */
function directoryApiUrl(Community $community, array $query = []): string
{
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);

    return $query === [] ? $path : $path.'?'.http_build_query($query);
}

/**
 * Sign in for the next request. A test keeps its guards from one request to the next: without new guards, a second
 * request could run as the person of the first one.
 */
function directoryActingAs(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->actingAs($user, 'web');
}

/**
 * @param  array<string, string|int>  $query
 */
function directoryPage(User $viewer, Community $community, array $query = []): TestResponse
{
    return directoryActingAs($viewer)->get(directoryPageUrl($community, $query));
}

/**
 * @param  array<string, string|int>  $query
 */
function directoryApi(User $viewer, Community $community, array $query = []): TestResponse
{
    return directoryActingAs($viewer)->getJson(directoryApiUrl($community, $query));
}

/**
 * A visit of the directory from the open page, as the browser sends it: the answer is JSON, with only the props that the
 * visit asks for. A search asks for `members`, `sort` and `query`; a reload after an event of the community asks for
 * `community`
 *
 * @param  list<string>  $only
 * @param  array<string, string|int>  $query
 */
function directoryVisit(User $viewer, Community $community, array $only, array $query = []): TestResponse
{
    return directoryActingAs($viewer)->get(directoryPageUrl($community, $query), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => DIRECTORY_COMPONENT,
        'X-Inertia-Partial-Data' => implode(',', $only),
    ]);
}

/**
 * The queries that a request runs
 *
 * @return list<string>
 */
function directoryQueriesOf(Closure $request): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    DB::disableQueryLog();

    return array_column(DB::getQueryLog(), 'query');
}

/**
 * The queries of a request that read the member list: the count and the page of CommunityMemberQuery
 *
 * @return list<string>
 */
function directoryMemberQueriesOf(Closure $request): array
{
    return array_values(array_filter(
        directoryQueriesOf($request),
        static fn (string $sql): bool => str_contains($sql, 'from "users"') && str_contains($sql, 'left join "community_members"'),
    ));
}

/**
 * The props of an Inertia page, as the browser gets them
 *
 * @return array<string, mixed>
 */
function directoryProps(TestResponse $response): array
{
    $response->assertOk();
    /** @var array{props: array<string, mixed>} $page */
    $page = $response->viewData('page');

    return $page['props'];
}

/**
 * The entries of the page
 *
 * @param  array<string, string|int>  $query
 * @return list<array<string, mixed>>
 */
function directoryItems(User $viewer, Community $community, array $query = []): array
{
    return directoryProps(directoryPage($viewer, $community, $query))['members']['items'];
}

/**
 * The usernames of the page, in its order
 *
 * @param  array<string, string|int>  $query
 * @return list<string>
 */
function directoryUsernames(User $viewer, Community $community, array $query = []): array
{
    return array_column(directoryItems($viewer, $community, $query), 'username');
}

/**
 * The usernames of the API list, in its order
 *
 * @param  array<string, string|int>  $query
 * @return list<string>
 */
function directoryApiUsernames(User $viewer, Community $community, array $query = []): array
{
    return directoryApi($viewer, $community, $query)->assertOk()->json('data.*.username');
}

/**
 * A new account that joins the community, at a date when one is given
 */
function directoryJoin(Community $community, string $username, string $name, ?string $joinedAt = null, KnowiiCommunityMemberRole $role = KnowiiCommunityMemberRole::Member): User
{
    $user = User::factory()->withUserProfile()->create(['username' => $username, 'name' => $name]);
    $community->users()->attach($user, ['role' => $role]);
    if ($joinedAt !== null) {
        DB::table('community_members')->where('community_id', $community->id)->where('user_id', $user->id)->update(['created_at' => $joinedAt, 'updated_at' => $joinedAt]);
    }

    return $user;
}

/**
 * A community with an owner only, created at a given date: the tests of the order add their own members
 */
function directoryEmptyCommunity(string $createdAt = '2026-01-01 10:00:00'): Community
{
    $owner = User::factory()->withUserProfile()->create(['username' => 'qx-owner', 'name' => 'Olive Qxowner']);
    $community = Community::factory()->create(['owner_id' => $owner->id]);
    $community->forceFill(['created_at' => $createdAt])->save();

    return $community;
}

/**
 * Give a member every profile field, with the choice to show them all or to hide them all
 */
function directoryFillProfile(User $member, bool $visible): void
{
    $member->forceFill(['email' => DIRECTORY_EMAIL_PROBE])->save();
    $member->profile->forceFill([
        'email' => DIRECTORY_EMAIL_PROBE,
        'headline' => DIRECTORY_HEADLINE_PROBE,
        'bio' => DIRECTORY_BIO_PROBE,
        'location' => DIRECTORY_LOCATION_PROBE,
        'phone' => DIRECTORY_PHONE_PROBE,
        'social_link_website' => 'https://example.com/'.DIRECTORY_LINK_PROBE,
        'field_visibility' => array_fill_keys(ProfileFieldVisibility::fields(), $visible),
    ])->save();
}

/**
 * The keys of an entry, sorted
 *
 * @param  array<string, mixed>  $entry
 * @return list<string>
 */
function directoryKeys(array $entry): array
{
    $keys = array_keys($entry);
    sort($keys);

    return $keys;
}

/**
 * The entry of a member in a list of entries
 *
 * @param  list<array<string, mixed>>  $entries
 * @return array<string, mixed>
 */
function directoryEntryOf(array $entries, User $member): array
{
    $found = array_values(array_filter($entries, static fn (array $entry): bool => $entry['cuid'] === $member->cuid));
    expect($found)->toHaveCount(1);

    return $found[0];
}

dataset('directory readers', ['reader', 'moderator', 'admin', 'owner']);

function directoryReader(string $who): User
{
    return $who === 'reader' ? test()->reader : test()->c[$who];
}

// ---------------------------------------------------------------------------------------------------------------------
// Who opens the directory
// ---------------------------------------------------------------------------------------------------------------------

test('a visitor goes to the sign-in page, and comes back to the directory after the sign-in (#148)', function () {
    $url = directoryPageUrl($this->community);

    app('auth')->forgetGuards();
    $this->get($url)->assertRedirect(route('login'));
    $this->post('/login', ['email' => $this->c['member']->email, 'password' => 'password'])->assertRedirect($url);
    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component(DIRECTORY_COMPONENT));
});

test('an account without a verified address goes to the page of the address check (#148)', function () {
    $unverified = User::factory()->unverified()->withUserProfile()->create();
    $this->community->users()->attach($unverified, ['role' => KnowiiCommunityMemberRole::Member]);

    directoryPage($unverified, $this->community)->assertRedirect(route('verification.notice'));
});

test('a signed-in person who is not a member gets the page without access, with no name of a member (#148)', function () {
    foreach (['owner', 'admin', 'moderator', 'member'] as $who) {
        $this->c[$who]->forceFill(['name' => 'DIRECTORY-NAME-PROBE '.$who, 'username' => 'directory-username-probe-'.$who])->save();
    }

    $response = directoryPage($this->c['outsider'], $this->community);

    $response->assertForbidden()->assertInertia(fn ($page) => $page->component('Communities/NoAccess')->missing('members'));
    expect((string) $response->getContent())->not->toContain('DIRECTORY-NAME-PROBE')
        ->and((string) $response->getContent())->not->toContain('directory-username-probe');
});

test('every member opens the directory, with any role (#148)', function (string $who) {
    directoryPage(directoryReader($who), $this->community)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component(DIRECTORY_COMPONENT)
            ->where('community.cuid', $this->community->cuid)
            ->has('spaceGroups')
            ->where('members.pagination.totalCount', 5)
            ->where('sort', 'name')
            ->where('query', ''));
})->with('directory readers');

test('the directory of a community that does not exist answers 404 (#148)', function () {
    directoryPage($this->c['member'], $this->community)->assertOk();

    directoryActingAs($this->c['member'])->get(route('members.index', ['communitySlug' => 'no-such-community-qx']))->assertNotFound();
});

test('the API list keeps its access: an account, a member, and the ability communities:read (#148)', function () {
    $url = directoryApiUrl($this->community);

    app('auth')->forgetGuards();
    $this->getJson($url)->assertUnauthorized();

    directoryApi($this->c['outsider'], $this->community)->assertForbidden();

    app('auth')->forgetGuards();
    Sanctum::actingAs($this->c['member'], ['posts:read']);
    $this->getJson($url)->assertForbidden();

    app('auth')->forgetGuards();
    Sanctum::actingAs($this->c['member'], ['communities:read']);
    $this->getJson($url)->assertOk()->assertJsonPath('metadata.pagination.totalCount', 5);
});

// ---------------------------------------------------------------------------------------------------------------------
// What an entry holds
// ---------------------------------------------------------------------------------------------------------------------

test('an entry holds seven fields and no other data of the member, on the page and in the API (#148)', function (string $who) {
    $member = $this->c['member'];
    directoryFillProfile($member, true);
    $reader = directoryReader($who);

    $page = directoryPage($reader, $this->community);
    $api = directoryApi($reader, $this->community, ['sort' => 'name']);

    foreach ([directoryProps($page)['members']['items'], $api->assertOk()->json('data')] as $entries) {
        expect($entries)->toHaveCount(5);
        foreach ($entries as $entry) {
            expect(directoryKeys($entry))->toBe(DIRECTORY_ENTRY_KEYS);
        }
        $entry = directoryEntryOf($entries, $member);
        expect($entry['name'])->toBe($member->name)
            ->and($entry['username'])->toBe($member->username)
            ->and($entry['role'])->toBe('member')
            // The headline is the only profile field of an entry (#1688): the member shows it here
            ->and($entry['headline'])->toBe(DIRECTORY_HEADLINE_PROBE);
    }

    foreach ([(string) $page->getContent(), (string) $api->getContent()] as $text) {
        expect($text)->not->toContain(DIRECTORY_EMAIL_PROBE)
            ->and($text)->not->toContain(DIRECTORY_BIO_PROBE)
            ->and($text)->not->toContain(DIRECTORY_LOCATION_PROBE)
            ->and($text)->not->toContain(DIRECTORY_PHONE_PROBE)
            ->and($text)->not->toContain(DIRECTORY_LINK_PROBE);
    }
})->with('directory readers');

test('a member who hides every profile field has the same seven fields, with an empty headline, for a member and for a manager (#148, #1688)', function (string $who) {
    $member = $this->c['member'];
    directoryFillProfile($member, false);
    $reader = directoryReader($who);

    $page = directoryPage($reader, $this->community);
    $api = directoryApi($reader, $this->community, ['sort' => 'name']);

    foreach ([directoryProps($page)['members']['items'], $api->assertOk()->json('data')] as $entries) {
        $entry = directoryEntryOf($entries, $member);
        expect(directoryKeys($entry))->toBe(DIRECTORY_ENTRY_KEYS)
            ->and($entry['headline'])->toBe('')
            ->and($entry['name'])->toBe($member->name);
    }

    foreach ([(string) $page->getContent(), (string) $api->getContent()] as $text) {
        expect($text)->not->toContain('DIRECTORY-HEADLINE-PROBE')
            ->and($text)->not->toContain(DIRECTORY_EMAIL_PROBE)
            ->and($text)->not->toContain(DIRECTORY_BIO_PROBE)
            ->and($text)->not->toContain(DIRECTORY_LOCATION_PROBE)
            ->and($text)->not->toContain(DIRECTORY_PHONE_PROBE)
            ->and($text)->not->toContain(DIRECTORY_LINK_PROBE);
    }
})->with('directory readers');

test('the owner is listed one time with the role owner; a person who left, a removed person, a pending invitation, a deleted account and a member of another community are not listed (#148)', function () {
    Event::fake([CommunityMembersChanged::class]);
    $community = $this->community;
    $reader = $this->reader;

    $listed = function () use ($community, $reader): array {
        $props = directoryProps(directoryPage($reader, $community));
        $api = directoryApi($reader, $community)->assertOk();
        expect($api->json('metadata.pagination.totalCount'))->toBe($props['members']['pagination']['totalCount'])
            ->and(collect($api->json('data.*.cuid'))->sort()->values()->all())->toBe(collect($props['members']['items'])->pluck('cuid')->sort()->values()->all());

        return ['cuids' => array_column($props['members']['items'], 'cuid'), 'total' => $props['members']['pagination']['totalCount']];
    };

    // The owner has no membership row: the list adds the owner, one time
    $start = $listed();
    $owners = array_values(array_filter(directoryItems($reader, $community), static fn (array $entry): bool => $entry['role'] === 'owner'));
    expect($start['total'])->toBe(5)
        ->and($owners)->toHaveCount(1)
        ->and($owners[0]['cuid'])->toBe($this->c['owner']->cuid);

    // A member of another community, and an account with a pending invitation to this one
    $elsewhere = Community::factory()->create();
    $elsewhere->users()->attach($this->c['outsider'], ['role' => KnowiiCommunityMemberRole::Member]);
    $invited = User::factory()->withUserProfile()->create();
    CommunityInvitation::query()->forceCreate(['cuid' => (new Cuid2)->toString(), 'community_id' => $community->id, 'email' => $invited->email, 'role' => KnowiiCommunityMemberRole::Member]);
    $now = $listed();
    expect($now['total'])->toBe(5)
        ->and($now['cuids'])->not->toContain($this->c['outsider']->cuid)
        ->and($now['cuids'])->not->toContain($invited->cuid);

    // A person who left
    $leaver = directoryJoin($community, 'qx-leaver', 'Lea Qxleaver');
    expect($listed()['total'])->toBe(6);
    app(LeavesCommunities::class)->leave($leaver, $community);
    $now = $listed();
    expect($now['total'])->toBe(5)->and($now['cuids'])->not->toContain($leaver->cuid);

    // A person whom a manager removed
    $removed = directoryJoin($community, 'qx-removed', 'Remy Qxremoved');
    expect($listed()['total'])->toBe(6);
    app(RemovesCommunityMembers::class)->remove($this->c['owner'], $community, $removed->cuid);
    $now = $listed();
    expect($now['total'])->toBe(5)->and($now['cuids'])->not->toContain($removed->cuid);

    // A deleted account: the delete action runs
    $deleted = User::factory()->withUserProfile()->withPersonalCommunity()->create(['username' => 'qx-deleted', 'name' => 'Dee Qxdeleted']);
    $community->users()->attach($deleted, ['role' => KnowiiCommunityMemberRole::Member]);
    expect($listed()['total'])->toBe(6);
    directoryActingAs($deleted)->delete('/user', ['password' => 'password']);
    expect(User::query()->whereKey($deleted->id)->exists())->toBeFalse();
    $now = $listed();
    expect($now['total'])->toBe(5)->and($now['cuids'])->not->toContain($deleted->cuid);
});

test('an owner who has a membership row by mistake is listed one time, as the owner (#148)', function () {
    $community = directoryEmptyCommunity();
    $owner = User::query()->findOrFail($community->owner_id);
    $viewer = directoryJoin($community, 'qx-viewer', 'Vera Qxviewer');
    directoryJoin($community, 'qx-admin', 'Adam Qxadmin', role: KnowiiCommunityMemberRole::Admin);
    $community->users()->attach($owner, ['role' => KnowiiCommunityMemberRole::Member]);
    expect(DB::table('community_members')->where('community_id', $community->id)->where('user_id', $owner->id)->count())->toBe(1);

    foreach (['name', 'newest'] as $sort) {
        $props = directoryProps(directoryPage($viewer, $community, ['sort' => $sort]));
        expect($props['members']['pagination']['totalCount'])->toBe(3)
            ->and(array_count_values(array_column($props['members']['items'], 'username')))->toBe(['qx-admin' => 1, 'qx-owner' => 1, 'qx-viewer' => 1])
            ->and(directoryEntryOf($props['members']['items'], $owner)['role'])->toBe('owner');
    }
    foreach (['role', 'name', 'newest'] as $sort) {
        $api = directoryApi($viewer, $community, ['sort' => $sort])->assertOk();
        expect($api->json('metadata.pagination.totalCount'))->toBe(3)
            ->and($api->json('data'))->toHaveCount(3)
            ->and(directoryEntryOf($api->json('data'), $owner)['role'])->toBe('owner');
    }
    // A search, and the team of the page "About"
    expect(directoryUsernames($viewer, $community, ['query' => 'qx-own']))->toBe(['qx-owner'])
        ->and(CommunityMemberQuery::team($community)->pluck('username')->all())->toBe(['qx-owner', 'qx-admin']);
});

test('a block changes nothing: each of the two people sees the other, and the answers are the ones of before (#148, ADR 0028)', function () {
    $a = $this->c['member'];
    $b = $this->reader;
    $read = fn (User $viewer): array => [
        'page' => directoryProps(directoryPage($viewer, $this->community))['members'],
        'api' => directoryApi($viewer, $this->community)->assertOk()->json(),
    ];
    $before = ['a' => $read($a), 'b' => $read($b)];

    $a->blockedUsers()->attach($b);
    expect($a->fresh()->hasBlockWith($b))->toBeTrue();

    $after = ['a' => $read($a->fresh()), 'b' => $read($b->fresh())];
    expect($after)->toBe($before)
        ->and(array_column($after['a']['page']['items'], 'cuid'))->toContain($b->cuid)
        ->and(array_column($after['b']['page']['items'], 'cuid'))->toContain($a->cuid);
});

test('an access group changes nothing: a member of a group and a member of no group are listed, and the answer names no group (#148)', function (string $who) {
    $group = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'DIRECTORY-GROUP-PROBE']);
    $group->members()->attach($this->c['member']);
    $reader = directoryReader($who);

    $page = directoryPage($reader, $this->community);
    $api = directoryApi($reader, $this->community);

    foreach ([directoryProps($page)['members']['items'], $api->assertOk()->json('data')] as $entries) {
        expect(array_column($entries, 'cuid'))->toContain($this->c['member']->cuid)
            ->and(array_column($entries, 'cuid'))->toContain($this->reader->cuid)
            ->and(directoryKeys(directoryEntryOf($entries, $this->c['member'])))->toBe(DIRECTORY_ENTRY_KEYS);
    }
    expect((string) $page->getContent())->not->toContain('DIRECTORY-GROUP-PROBE')
        ->and((string) $api->getContent())->not->toContain('DIRECTORY-GROUP-PROBE');
})->with(['reader', 'owner']);

// ---------------------------------------------------------------------------------------------------------------------
// The search
// ---------------------------------------------------------------------------------------------------------------------

/**
 * A community with fixed names for the tests of the search: the owner, a reader, "qxada", a name with `%` and a name with
 * a word that starts with a backslash. A person with the name of "qxada" is in another community
 *
 * @return array{community: Community, viewer: User, ada: User}
 */
function directorySearchCommunity(): array
{
    $community = directoryEmptyCommunity();
    $viewer = directoryJoin($community, 'qx-viewer', 'Vera Qxviewer');
    $ada = directoryJoin($community, 'qxada', 'Ada Qxlovelace');
    directoryJoin($community, 'qxpercent', '100% Qxsure');
    directoryJoin($community, 'qxslash', 'Zoe \\Qxback');
    $twin = User::factory()->withUserProfile()->create(['username' => 'qxada-outside', 'name' => 'Ada Qxlovelace']);
    Community::factory()->create()->users()->attach($twin, ['role' => KnowiiCommunityMemberRole::Member]);

    return ['community' => $community, 'viewer' => $viewer, 'ada' => $ada];
}

/**
 * The usernames that a search finds. One search for the page and for the API, and the count follows the search
 *
 * @return list<string>
 */
function directoryFind(User $viewer, Community $community, string $text): array
{
    $props = directoryProps(directoryPage($viewer, $community, ['query' => $text]));
    $api = directoryApi($viewer, $community, ['query' => $text, 'sort' => 'name'])->assertOk();
    $usernames = array_column($props['members']['items'], 'username');
    expect($api->json('data.*.username'))->toBe($usernames)
        ->and($props['members']['pagination']['totalCount'])->toBe(count($usernames))
        ->and($api->json('metadata.pagination.totalCount'))->toBe(count($usernames));

    return $usernames;
}

test('the search reads the start of the username and the start of a word of the name, in the community only (#148)', function () {
    ['community' => $community, 'viewer' => $viewer] = directorySearchCommunity();
    $find = static fn (string $text): array => directoryFind($viewer, $community, $text);

    // The start of the username, and the start of the second word of the name
    expect($find('qxad'))->toBe(['qxada'])
        ->and($find('qxlove'))->toBe(['qxada'])
        // Capital letters in the text, and spaces around it
        ->and($find('QXLOVE'))->toBe(['qxada'])
        ->and($find('  Ada Qxl  '))->toBe(['qxada'])
        // The middle of a word finds nobody
        ->and($find('lovelace'))->toBe([])
        ->and($find('xada'))->toBe([])
        // The wildcards of the input are literal
        ->and($find('%'))->toBe([])
        ->and($find('qxad_'))->toBe([])
        ->and($find('_xada'))->toBe([])
        ->and($find('100%'))->toBe(['qxpercent'])
        // A backslash is a plain character too: it does not escape the character after it
        ->and($find('\\'))->toBe(['qxslash'])
        ->and($find('\\qxb'))->toBe(['qxslash'])
        ->and($find('\\%'))->toBe([])
        ->and($find('\\_xback'))->toBe([])
        ->and($find('qxad\\'))->toBe([])
        ->and($find('q\\xada'))->toBe([])
        // The person outside the community is never found, and never counted
        ->and($find('Ada Qxlovelace'))->toBe(['qxada'])
        ->and($find('qxada-outside'))->toBe([]);

    // An empty text gives every member
    expect($find(''))->toHaveCount(5)->and($find('   '))->toHaveCount(5);
});

test('the search reads no email address and no profile field, also when the member shows them all (#148, #1688)', function () {
    ['community' => $community, 'viewer' => $viewer, 'ada' => $ada] = directorySearchCommunity();
    directoryFillProfile($ada, true);
    $find = static fn (string $text): array => directoryFind($viewer, $community, $text);

    // The member is found by name, and the entry holds the headline that the member shows
    expect($find('qxad'))->toBe(['qxada'])
        ->and(directoryEntryOf(directoryItems($viewer, $community), $ada)['headline'])->toBe(DIRECTORY_HEADLINE_PROBE);

    // The email address
    expect($find(DIRECTORY_EMAIL_PROBE))->toBe([])
        ->and($find('directory-email'))->toBe([])
        // The headline is not searched (#1688): the whole text, its first word, another word
        ->and($find(DIRECTORY_HEADLINE_PROBE))->toBe([])
        ->and($find('DIRECTORY-HEADLINE'))->toBe([])
        ->and($find('tomatoes'))->toBe([])
        // The bio and the location
        ->and($find(DIRECTORY_BIO_PROBE))->toBe([])
        ->and($find('qxbiography'))->toBe([])
        ->and($find(DIRECTORY_LOCATION_PROBE))->toBe([])
        // The phone number: the whole text, and a group of digits
        ->and($find(DIRECTORY_PHONE_PROBE))->toBe([])
        ->and($find('+32'))->toBe([])
        ->and($find('000'))->toBe([])
        // A link: the address, its start, and its last part
        ->and($find('https://example.com/'.DIRECTORY_LINK_PROBE))->toBe([])
        ->and($find('https'))->toBe([])
        ->and($find(DIRECTORY_LINK_PROBE))->toBe([]);
});

test('a search text of 300 characters answers 200, and the server keeps its first 100 characters (#148)', function () {
    $long = str_repeat('q', 300);

    $props = directoryProps(directoryPage($this->reader, $this->community, ['query' => $long]));
    expect($props['query'])->toBe(str_repeat('q', Constants::MEMBER_SEARCH_MAX_LENGTH))
        ->and($props['members']['items'])->toBe([]);
    directoryApi($this->reader, $this->community, ['query' => $long])->assertOk()->assertJsonCount(0, 'data');

    // The cut text is the one that is searched: a name of 100 characters is found with 300
    $name = 'Q'.str_repeat('q', Constants::MEMBER_SEARCH_MAX_LENGTH - 1);
    $this->c['member']->forceFill(['name' => $name])->save();
    expect(directoryUsernames($this->reader, $this->community, ['query' => $long]))->toBe([$this->c['member']->username])
        ->and(directoryApiUsernames($this->reader, $this->community, ['query' => $long]))->toBe([$this->c['member']->username]);

    // A text that is not a text is an empty search
    directoryActingAs($this->reader)->get(directoryPageUrl($this->community).'?query[]=a&sort[]=name')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('query', '')->where('sort', 'name')->where('members.pagination.totalCount', 5));
    directoryActingAs($this->reader)->getJson(directoryApiUrl($this->community).'?query[]=a&sort[]=name')
        ->assertOk()
        ->assertJsonPath('metadata.pagination.totalCount', 5);
});

// ---------------------------------------------------------------------------------------------------------------------
// The sort
// ---------------------------------------------------------------------------------------------------------------------

test('the sort "name" goes from A to Z without the case, then by username (#148)', function () {
    $community = directoryEmptyCommunity();
    $viewer = directoryJoin($community, 'qx-viewer', 'vera Qxviewer');
    directoryJoin($community, 'qx-zed', 'Zed Qxlast', role: KnowiiCommunityMemberRole::Admin);
    directoryJoin($community, 'qx-twin-b', 'Sam Qxtwin');
    directoryJoin($community, 'qx-twin-a', 'Sam Qxtwin');
    directoryJoin($community, 'qx-anna', 'anna Qxfirst');
    directoryJoin($community, 'qx-bob', 'Bob Qxsecond', role: KnowiiCommunityMemberRole::Moderator);

    $expected = ['qx-anna', 'qx-bob', 'qx-owner', 'qx-twin-a', 'qx-twin-b', 'qx-viewer', 'qx-zed'];

    expect(directoryUsernames($viewer, $community, ['sort' => 'name']))->toBe($expected)
        ->and(directoryApiUsernames($viewer, $community, ['sort' => 'name']))->toBe($expected);
});

test('the sort "newest" gives the newest membership first, and the owner at the date of the community (#148)', function () {
    $community = directoryEmptyCommunity('2026-03-01 10:00:00');
    $viewer = directoryJoin($community, 'qx-viewer', 'Vera Qxviewer', '2026-02-01 10:00:00');
    directoryJoin($community, 'qx-newest', 'Nina Qxnewest', '2026-06-01 10:00:00');
    directoryJoin($community, 'qx-same-b', 'Sam Qxsame', '2026-04-01 10:00:00');
    directoryJoin($community, 'qx-same-a', 'Sal Qxsame', '2026-04-01 10:00:00');
    directoryJoin($community, 'qx-oldest', 'Otto Qxoldest', '2026-01-01 10:00:00');

    $expected = ['qx-newest', 'qx-same-a', 'qx-same-b', 'qx-owner', 'qx-viewer', 'qx-oldest'];

    $props = directoryProps(directoryPage($viewer, $community, ['sort' => 'newest']));
    expect(array_column($props['members']['items'], 'username'))->toBe($expected)
        ->and($props['sort'])->toBe('newest')
        ->and(directoryApiUsernames($viewer, $community, ['sort' => 'newest']))->toBe($expected);
});

test('the API without a sort, and with an unknown one, keeps the order of the roles; the page then sorts by name (#148)', function () {
    $community = directoryEmptyCommunity();
    $viewer = directoryJoin($community, 'qx-viewer', 'Vera Qxviewer');
    directoryJoin($community, 'qx-zed', 'Adam Qxadmin', role: KnowiiCommunityMemberRole::Admin);
    directoryJoin($community, 'qx-mod', 'Bea Qxmoderator', role: KnowiiCommunityMemberRole::Moderator);
    directoryJoin($community, 'qx-anna', 'Zoe Qxmember');

    $byRole = ['qx-owner', 'qx-zed', 'qx-mod', 'qx-anna', 'qx-viewer'];
    $byName = ['qx-zed', 'qx-mod', 'qx-owner', 'qx-viewer', 'qx-anna'];

    expect(directoryApiUsernames($viewer, $community))->toBe($byRole)
        ->and(directoryApiUsernames($viewer, $community, ['sort' => 'unknown']))->toBe($byRole)
        ->and(directoryApiUsernames($viewer, $community, ['sort' => 'role']))->toBe($byRole)
        ->and(directoryApiUsernames($viewer, $community, ['sort' => 'NAME']))->toBe($byRole)
        ->and(directoryUsernames($viewer, $community))->toBe($byName)
        ->and(directoryUsernames($viewer, $community, ['sort' => 'unknown']))->toBe($byName)
        // The page never shows the order of the roles
        ->and(directoryUsernames($viewer, $community, ['sort' => 'role']))->toBe($byName);

    expect(directoryProps(directoryPage($viewer, $community, ['sort' => 'unknown']))['sort'])->toBe('name')
        ->and(directoryProps(directoryPage($viewer, $community, ['sort' => 'role']))['sort'])->toBe('name');
});

test('with a search, the sort "role" puts the username matches first, and the sorts "name" and "newest" do not (#148)', function () {
    $community = directoryEmptyCommunity();
    $viewer = directoryJoin($community, 'qx-viewer', 'Vera Qxviewer');
    // The username of the first one starts with the text, the name of the second one has a word that starts with it
    directoryJoin($community, 'qxmatch', 'Zed Other', '2026-02-01 10:00:00');
    directoryJoin($community, 'qx-other', 'Anna Qxmatch', '2026-03-01 10:00:00');

    expect(directoryApiUsernames($viewer, $community, ['query' => 'qxmatch']))->toBe(['qxmatch', 'qx-other'])
        ->and(directoryApiUsernames($viewer, $community, ['query' => 'qxmatch', 'sort' => 'name']))->toBe(['qx-other', 'qxmatch'])
        ->and(directoryUsernames($viewer, $community, ['query' => 'qxmatch']))->toBe(['qx-other', 'qxmatch'])
        ->and(directoryUsernames($viewer, $community, ['query' => 'qxmatch', 'sort' => 'newest']))->toBe(['qx-other', 'qxmatch'])
        ->and(directoryApiUsernames($viewer, $community, ['query' => 'qxmatch', 'sort' => 'newest']))->toBe(['qx-other', 'qxmatch']);
});

// ---------------------------------------------------------------------------------------------------------------------
// The pages of the list, and their cost
// ---------------------------------------------------------------------------------------------------------------------

test('25 members give 20 entries with the page, and the API gives the 5 others (#148)', function () {
    for ($i = 1; $i <= 20; $i++) {
        directoryJoin($this->community, sprintf('qx-page-%02d', $i), sprintf('Member Qxpage %02d', $i));
    }

    $props = directoryProps(directoryPage($this->reader, $this->community));
    $first = array_column($props['members']['items'], 'cuid');
    expect($first)->toHaveCount(Constants::COMMUNITY_MEMBERS_PAGE_SIZE)
        ->and(Constants::COMMUNITY_MEMBERS_PAGE_SIZE)->toBe(20)
        ->and($props['members']['pagination']['nextOffset'])->toBe(20)
        ->and($props['members']['pagination']['totalCount'])->toBe(25)
        ->and($props['members']['pagination']['limit'])->toBe(20);

    $api = directoryApi($this->reader, $this->community, ['sort' => 'name', 'limit' => 20, 'offset' => 20])->assertOk();
    $second = $api->json('data.*.cuid');
    expect($second)->toHaveCount(5)
        ->and($api->json('metadata.pagination.nextOffset'))->toBeNull()
        ->and(array_intersect($first, $second))->toBe([])
        ->and(array_unique([...$first, ...$second]))->toHaveCount(25);

    // The API keeps its maximum
    directoryApi($this->reader, $this->community, ['limit' => 500])
        ->assertOk()
        ->assertJsonPath('metadata.pagination.limit', Constants::PAGINATION_MAX_LIMIT)
        ->assertJsonCount(25, 'data');

    // The page gives its first entries only: `limit` and `offset` of its address change nothing
    expect(directoryItems($this->reader, $this->community, ['limit' => 100, 'offset' => 5]))->toHaveCount(20);
});

test('the page and the API cost the same number of queries with 3 members and with 30 (#148)', function () {
    $community = directoryEmptyCommunity();
    $viewer = directoryJoin($community, 'qx-viewer', 'Vera Qxviewer');
    directoryJoin($community, 'qx-second', 'Sam Qxsecond')->profile->forceFill(['headline' => 'A headline'])->save();

    $count = static fn (Closure $request): int => count(directoryQueriesOf($request));
    $page = fn () => directoryPage($viewer->fresh(), $community)->assertOk();
    $api = fn () => directoryApi($viewer->fresh(), $community, ['sort' => 'name', 'limit' => 100])->assertOk();

    // The first visit of a day writes the date of the visit of the account: it is not a query of the list
    $page();

    $pageWith3 = $count($page);
    $apiWith3 = $count($api);

    for ($i = 1; $i <= 27; $i++) {
        directoryJoin($community, sprintf('qx-cost-%02d', $i), sprintf('Member Qxcost %02d', $i))->profile->forceFill(['headline' => 'Headline '.$i])->save();
    }
    expect(directoryProps($page())['members']['pagination']['totalCount'])->toBe(30)
        ->and($api()->json('data'))->toHaveCount(30);

    expect($count($page))->toBe($pageWith3)
        ->and($count($api))->toBe($apiWith3);

    // The list costs two queries on the members (the count and the page), with or without a search
    expect(directoryMemberQueriesOf($page))->toHaveCount(2)
        ->and(directoryMemberQueriesOf($api))->toHaveCount(2)
        ->and(directoryMemberQueriesOf(fn () => directoryVisit($viewer->fresh(), $community, ['members', 'sort', 'query'], ['query' => 'qx-cost'])->assertOk()))->toHaveCount(2);
});

test('a reload of the page that does not ask for the list runs no query on the members (#148)', function () {
    $community = directoryEmptyCommunity();
    $viewer = directoryJoin($community, 'qx-viewer', 'Vera Qxviewer');
    directoryPage($viewer, $community)->assertOk();

    // The reload of the community after an event of its channel, and the one of the navigation
    foreach ([['community'], ['spaceGroups', 'space'], ['canManageMembers']] as $only) {
        $response = null;
        $queries = directoryMemberQueriesOf(function () use (&$response, $viewer, $community, $only) {
            $response = directoryVisit($viewer->fresh(), $community, $only)->assertOk();
        });

        expect($queries)->toBe([])
            ->and($response->json('component'))->toBe(DIRECTORY_COMPONENT)
            ->and(array_intersect(['members', 'sort', 'query'], array_keys($response->json('props'))))->toBe([]);
    }
    expect(directoryVisit($viewer->fresh(), $community, ['community'])->json('props.community.cuid'))->toBe($community->cuid);

    // One of the three props of the list reads the list, one time for the three
    $response = null;
    $queries = directoryMemberQueriesOf(function () use (&$response, $viewer, $community) {
        $response = directoryVisit($viewer->fresh(), $community, ['members', 'sort', 'query'], ['sort' => 'newest', 'query' => 'qx-v'])->assertOk();
    });
    expect($queries)->toHaveCount(2)
        ->and($response->json('props.members.items.*.username'))->toBe(['qx-viewer'])
        ->and($response->json('props.sort'))->toBe('newest')
        ->and($response->json('props.query'))->toBe('qx-v')
        ->and(array_intersect(['community', 'spaceGroups', 'canManageMembers'], array_keys($response->json('props'))))->toBe([]);
    expect(directoryMemberQueriesOf(fn () => directoryVisit($viewer->fresh(), $community, ['sort'])->assertOk()))->toHaveCount(2);
});

test('a search visit of the page asks for the list only: the other props are not built again (#148)', function () {
    $community = directoryEmptyCommunity();
    $viewer = directoryJoin($community, 'qx-viewer', 'Vera Qxviewer');
    $found = directoryJoin($community, 'qx-found', 'Fay Qxfound');

    directoryPage($viewer, $community, ['query' => 'qx-found'])
        ->assertInertia(fn ($page) => $page
            ->component(DIRECTORY_COMPONENT)
            ->has('community')
            ->has('spaceGroups')
            ->has('canManageMembers')
            ->reloadOnly(['members', 'sort', 'query'], fn ($reload) => $reload
                ->has('members.items', 1)
                ->where('members.items.0.cuid', $found->cuid)
                ->where('sort', 'name')
                ->where('query', 'qx-found')
                ->missing('community')
                ->missing('spaceGroups')
                ->missing('canManageMembers')));
});

// ---------------------------------------------------------------------------------------------------------------------
// The limiter
// ---------------------------------------------------------------------------------------------------------------------

test('the member lists are limited per minute and per account, on the page and in the API, and the Markdown preview keeps its own budget (#148)', function () {
    expect(Constants::MEMBER_LISTS_PER_MINUTE)->toBe(60);
    // The clock stands still: the test does not depend on the time that its requests take
    $this->freezeTime();

    // The page
    $this->actingAs($this->c['member']);
    for ($i = 0; $i < Constants::MEMBER_LISTS_PER_MINUTE; $i++) {
        $this->get(directoryPageUrl($this->community))->assertOk();
    }
    // The 61st request: 429 with the wait, and no page of the application. The page handles this answer itself
    $refused = $this->get(directoryPageUrl($this->community))->assertStatus(429);
    expect($refused->headers->get('Retry-After'))->toBe('60')
        ->and($refused->headers->has('X-Inertia'))->toBeFalse()
        ->and((string) $refused->headers->get('Content-Type'))->toStartWith('text/html');
    // A search visit from the open page gets the same answer: it is not an answer of Inertia, and it holds no member
    $search = directoryVisit($this->c['member'], $this->community, ['members', 'sort', 'query'], ['query' => 'a'])->assertStatus(429);
    expect($search->headers->get('Retry-After'))->toBe('60')
        ->and($search->headers->has('X-Inertia'))->toBeFalse()
        ->and((string) $search->getContent())->not->toContain($this->c['member']->cuid);
    // The page and the API share the budget
    $this->getJson(directoryApiUrl($this->community))->assertStatus(429);
    // A Markdown preview still answers
    $this->postJson(ApiRoutes::path(ApiRoutes::MARKDOWN_PREVIEW), ['markdown' => '**hi**'])->assertOk();

    // The API, for another account
    directoryActingAs($this->reader);
    for ($i = 0; $i < Constants::MEMBER_LISTS_PER_MINUTE; $i++) {
        $this->getJson(directoryApiUrl($this->community))->assertOk();
    }
    $this->getJson(directoryApiUrl($this->community))->assertStatus(429);
    $this->postJson(ApiRoutes::path(ApiRoutes::MARKDOWN_PREVIEW), ['markdown' => '**hi**'])->assertOk();

    // The budget is for one minute: the first account reads the list again when the minute is over
    $this->travel(59)->seconds();
    directoryPage($this->c['member'], $this->community)->assertStatus(429)->assertHeader('Retry-After', '1');
    $this->travel(1)->seconds();
    directoryPage($this->c['member'], $this->community)->assertOk();
});

test('a reload of the page that reads no member list does not use the budget of the member lists (#148)', function () {
    $this->freezeTime();
    $member = $this->c['member'];

    // More reloads of the community than the budget of a minute: each one answers, and none is counted
    for ($i = 0; $i < Constants::MEMBER_LISTS_PER_MINUTE + 5; $i++) {
        directoryVisit($member, $this->community, ['community'])->assertOk()->assertHeader('X-RateLimit-Remaining', (string) Constants::MEMBER_LISTS_PER_MINUTE);
    }

    // The whole budget is still there for the list: 60 searches, then 429
    for ($i = 0; $i < Constants::MEMBER_LISTS_PER_MINUTE; $i++) {
        directoryVisit($member, $this->community, ['members', 'sort', 'query'])
            ->assertOk()
            ->assertHeader('X-RateLimit-Remaining', (string) (Constants::MEMBER_LISTS_PER_MINUTE - $i - 1));
    }
    directoryVisit($member, $this->community, ['members', 'sort', 'query'])->assertStatus(429);
    // Over the limit, every request of the page answers 429, also one that asks for no list
    directoryVisit($member, $this->community, ['community'])->assertStatus(429);

    // A person who is not a member, and an address without a community, are counted: they read no list, and they are no
    // reload of the page
    $outsider = $this->c['outsider'];
    directoryPage($outsider, $this->community)->assertForbidden()->assertHeader('X-RateLimit-Remaining', (string) (Constants::MEMBER_LISTS_PER_MINUTE - 1));
    directoryActingAs($outsider)->get(route('members.index', ['communitySlug' => 'no-such-community-qx']))
        ->assertNotFound()
        ->assertHeader('X-RateLimit-Remaining', (string) (Constants::MEMBER_LISTS_PER_MINUTE - 2));
});

// ---------------------------------------------------------------------------------------------------------------------
// "Manage members"
// ---------------------------------------------------------------------------------------------------------------------

test('the page tells who can manage the members: the owner, an admin, and a moderator with the right (#148)', function () {
    $can = fn (User $viewer): bool => directoryProps(directoryPage($viewer->fresh(), $this->community))['canManageMembers'];

    expect($can($this->c['owner']))->toBeTrue()
        ->and($can($this->c['admin']))->toBeTrue()
        ->and($can($this->c['moderator']))->toBeFalse()
        ->and($can($this->c['member']))->toBeFalse();

    $this->community->update(['moderator_permissions' => [...$this->community->moderatorPermissions(), 'manage_members' => true]]);

    expect($can($this->c['moderator']))->toBeTrue()
        ->and($can($this->c['member']))->toBeFalse();
});
