<?php

use App\ApiRoutes;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Realtime\ReadsPresenceChannels;
use App\Contracts\Users\UpdatesMemberListVisibility;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiNotificationKind;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceTreeChanged;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Community;
use App\Models\Notification;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
 * The member setting "Show me in the member list" (#1466): one switch per account (`users.show_in_member_list`). A hidden
 * member is not in the member lists of the other members: the directory, the API list, the search, the member fields and
 * the mention suggestions. The managers (the owner, the admins and the moderators who manage members) and the person
 * themself still get the entry, with `in_member_list` false. The team of a community is always listed. The member page,
 * a typed mention, the online list and the member count do not change.
 */

// A name and a username that no generator makes: a test that finds them found the hidden member
const MLV_HIDDEN_NAME = 'Quillon Zeffwren';
const MLV_HIDDEN_NAME_START = 'Quillo';
const MLV_HIDDEN_USERNAME = 'qz-zeffwren-hid';
const MLV_HIDDEN_EMAIL = 'qz-zeffwren-hid@example.com';

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    // The moderators of this community manage the members: the moderator is a manager (MM). mlvReader('mo') takes the
    // right away, because the right belongs to the community and not to one moderator (MO)
    mlvModeratorsManageMembers($this->community, true);
    $this->hidden = User::factory()->withUserProfile()->create([
        'name' => MLV_HIDDEN_NAME,
        'username' => MLV_HIDDEN_USERNAME,
        'email' => MLV_HIDDEN_EMAIL,
        'show_in_member_list' => false,
    ]);
    $this->community->users()->attach($this->hidden, ['role' => KnowiiCommunityMemberRole::Member]);
});

function mlvModeratorsManageMembers(Community $community, bool $allowed): void
{
    $community->update(['moderator_permissions' => [...$community->moderatorPermissions(), 'manage_members' => $allowed]]);
}

/**
 * The readers of the tests: `member` (M, a plain member), `mo` (the moderator without the right "manage members"),
 * `mm` (the moderator with it), `admin` and `owner`
 */
function mlvReader(string $who): User
{
    $test = test();
    if ($who === 'mo') {
        mlvModeratorsManageMembers($test->community, false);
    }

    return match ($who) {
        'member' => $test->c['member'],
        'mo', 'mm' => $test->c['moderator'],
        'admin' => $test->c['admin'],
        'owner' => $test->c['owner'],
        'hidden' => $test->hidden,
    };
}

/**
 * Sign in for the next request, with new guards: a second request could run as the person of the first one
 */
function mlvActingAs(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->actingAs($user->fresh() ?? $user, 'web');
}

/**
 * @param  array<string, string|int>  $query
 */
function mlvApi(User $reader, Community $community, array $query = []): TestResponse
{
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);

    return mlvActingAs($reader)->getJson($query === [] ? $path : $path.'?'.http_build_query($query));
}

/**
 * @param  array<string, string|int>  $query
 */
function mlvPage(User $reader, Community $community, array $query = []): TestResponse
{
    return mlvActingAs($reader)->get(route('members.index', ['communitySlug' => $community->slug, ...$query]));
}

/**
 * The props of an Inertia page
 *
 * @return array<string, mixed>
 */
function mlvProps(TestResponse $response): array
{
    $response->assertOk();
    /** @var array{props: array<string, mixed>} $page */
    $page = $response->viewData('page');

    return $page['props'];
}

/**
 * The entry of a person in a list of entries, or null
 *
 * @param  list<array<string, mixed>>  $entries
 * @return array<string, mixed>|null
 */
function mlvEntryOf(array $entries, User $person): ?array
{
    $found = array_values(array_filter($entries, static fn (array $entry): bool => $entry['cuid'] === $person->cuid));
    expect(count($found))->toBeLessThanOrEqual(1);

    return $found[0] ?? null;
}

/**
 * The text of an answer holds nothing of the hidden member
 */
function mlvExpectNoTraceOf(string $text, User $hidden): void
{
    expect($text)->not->toContain($hidden->cuid)
        ->and($text)->not->toContain(MLV_HIDDEN_NAME)
        ->and($text)->not->toContain(MLV_HIDDEN_USERNAME);
}

/**
 * The queries that a request runs
 */
function mlvQueryCount(Closure $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

/**
 * The props of the Messages page, with an address that may hold `to`
 *
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function mlvMessagesProps(User $reader, Community $community, array $query): array
{
    return mlvProps(mlvActingAs($reader)->get(route('messages.index', ['communitySlug' => $community->slug, ...$query])));
}

dataset('mlv plain readers', ['member', 'mo']);
dataset('mlv managers', ['owner', 'admin', 'mm']);

// ---------------------------------------------------------------------------------------------------------------------
// The setting
// ---------------------------------------------------------------------------------------------------------------------

test('a new account is shown in the member lists, and so is a row created without the column (#1466)', function () {
    $created = app(CreatesNewUsers::class)->create([
        'name' => 'Grace Qzregister',
        'email' => 'grace-qzregister@example.com',
        'password' => 'a-Long-Password-9!',
        'password_confirmation' => 'a-Long-Password-9!',
        'terms' => true,
    ]);
    expect($created->refresh()->show_in_member_list)->toBeTrue()
        ->and($created->showsInMemberList())->toBeTrue();

    DB::table('users')->insert(['cuid' => 'cuid-qz-without-column', 'username' => 'qz-without-column', 'name' => 'Row Qzwithout', 'email' => 'qz-without-column@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    $row = User::where('username', 'qz-without-column')->firstOrFail();
    expect($row->show_in_member_list)->toBeTrue()
        ->and($row->showsInMemberList())->toBeTrue()
        // A model built in memory does not hold the column yet
        ->and((new User)->showsInMemberList())->toBeTrue();
});

test('a member hides from the member list and shows again; a wrong value is refused (#1466)', function () {
    $member = $this->c['member'];
    Log::spy();
    mlvActingAs($member);
    $path = ApiRoutes::path(ApiRoutes::USER_MEMBER_LIST);

    $this->patchJson($path, ['show_in_member_list' => false])
        ->assertOk()
        ->assertJsonPath('data.show_in_member_list', false)
        ->assertJsonPath('data.cuid', $member->cuid);
    expect($member->refresh()->show_in_member_list)->toBeFalse();

    $this->patchJson($path, ['show_in_member_list' => true])->assertOk()->assertJsonPath('data.show_in_member_list', true);
    expect($member->refresh()->show_in_member_list)->toBeTrue();

    // The application answers every validation issue with 400 (ApiResponses::validationIssue)
    $this->patchJson($path, [])->assertStatus(400)->assertJsonPath('errors.show_in_member_list.0', fn ($message) => is_string($message));
    $this->patchJson($path, ['show_in_member_list' => 'yes'])->assertStatus(400);
    expect($member->refresh()->show_in_member_list)->toBeTrue();

    // The log line names the account by its cuid, never by its name
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'Member list visibility changed' && $context === ['user' => $member->cuid, 'shown' => false])->once();
    Log::shouldNotHaveReceived('info', fn (string $message, array $context = []): bool => $message === 'Member list visibility changed' && in_array($member->name, $context, true));
});

test('the route needs an account, and a token needs the ability user:write (#1466)', function () {
    $path = ApiRoutes::path(ApiRoutes::USER_MEMBER_LIST);

    $this->patchJson($path, ['show_in_member_list' => false])->assertUnauthorized();

    Sanctum::actingAs($this->c['member'], ['user:read']);
    $this->patchJson($path, ['show_in_member_list' => false])->assertForbidden();
    expect($this->c['member']->refresh()->show_in_member_list)->toBeTrue();

    app('auth')->forgetGuards();
    Sanctum::actingAs($this->c['member'], ['user:write']);
    $this->patchJson($path, ['show_in_member_list' => false])->assertOk()->assertJsonPath('data.show_in_member_list', false);
});

test('the choice is in the answer about the current user only, never in an answer about another person (#1466)', function () {
    mlvActingAs($this->hidden)->getJson(ApiRoutes::path(ApiRoutes::USER))->assertOk()->assertJsonPath('data.show_in_member_list', false);

    $group = SpaceGroup::factory()->create(['community_id' => $this->community->id]);
    $space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion]);
    $post = app(CreatesPosts::class)->create($this->hidden, $space, ['title' => 'A post of the hidden member', 'body' => 'Hello']);

    // Every prop of the member page but `auth`, which is the reader's own account
    $memberPage = mlvProps(mlvActingAs($this->c['member'])->get(route('members.show', ['communitySlug' => $this->community->slug, 'username' => $this->hidden->username])));
    unset($memberPage['auth']);
    $answers = [
        (string) json_encode($memberPage),
        (string) mlvActingAs($this->c['member'])->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POST, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid, ApiRoutes::PARAM_POST => $post->cuid]))->assertOk()->getContent(),
        (string) mlvApi($this->c['owner'], $this->community)->assertOk()->getContent(),
    ];
    foreach ($answers as $answer) {
        // The member page, the post and the list name the hidden member: the choice is still not in them
        expect($answer)->toContain($this->hidden->cuid)->not->toContain('show_in_member_list');
    }
});

// ---------------------------------------------------------------------------------------------------------------------
// Who is in the member list
// ---------------------------------------------------------------------------------------------------------------------

test('a plain member never finds a hidden member: in the API list, the search, each sort, each page and the directory (#1466)', function (string $who) {
    $reader = mlvReader($who);
    $hidden = $this->hidden;

    foreach (['role', 'name', 'newest'] as $sort) {
        foreach ([0, 20] as $offset) {
            foreach (['', MLV_HIDDEN_NAME_START, MLV_HIDDEN_USERNAME, strtoupper(MLV_HIDDEN_USERNAME)] as $search) {
                $query = ['sort' => $sort, 'offset' => $offset, 'limit' => 20, ...($search === '' ? [] : ['query' => $search])];
                $answer = mlvApi($reader, $this->community, $query)->assertOk();
                mlvExpectNoTraceOf((string) $answer->getContent(), $hidden);
                if ($search === '' && $offset === 0) {
                    // The owner, the admin, the moderator and the plain member: the hidden member is not counted
                    expect($answer->json('metadata.pagination.totalCount'))->toBe(4)
                        ->and($answer->json('data'))->toHaveCount(4);
                }
            }
        }
    }

    foreach ([[], ['sort' => 'newest'], ['query' => MLV_HIDDEN_NAME_START], ['query' => MLV_HIDDEN_USERNAME]] as $query) {
        $page = mlvPage($reader, $this->community, $query);
        // A search page gives back the text of the reader (the prop `query` and the address): its members hold nothing
        mlvExpectNoTraceOf(isset($query['query']) ? (string) json_encode(mlvProps($page)['members']) : (string) $page->getContent(), $hidden);
        if ($query === []) {
            expect(mlvProps($page)['members']['pagination']['totalCount'])->toBe(4)
                ->and(mlvProps($page)['hiddenFromList'])->toBeFalse();
        }
    }
})->with('mlv plain readers');

test('the managers get the hidden member everywhere, with in_member_list false; every other entry has true (#1466)', function (string $who) {
    $reader = mlvReader($who);

    $lists = [
        mlvApi($reader, $this->community)->assertOk()->json('data'),
        mlvApi($reader, $this->community, ['sort' => 'name'])->assertOk()->json('data'),
        mlvApi($reader, $this->community, ['query' => MLV_HIDDEN_NAME_START])->assertOk()->json('data'),
        mlvApi($reader, $this->community, ['query' => MLV_HIDDEN_USERNAME])->assertOk()->json('data'),
        mlvProps(mlvPage($reader, $this->community))['members']['items'],
        mlvProps(mlvPage($reader, $this->community, ['query' => MLV_HIDDEN_USERNAME]))['members']['items'],
    ];
    foreach ($lists as $entries) {
        expect(mlvEntryOf($entries, $this->hidden))->not->toBeNull()
            ->and(mlvEntryOf($entries, $this->hidden)['in_member_list'])->toBeFalse();
        foreach ($entries as $entry) {
            if ($entry['cuid'] !== $this->hidden->cuid) {
                expect($entry['in_member_list'])->toBeTrue();
            }
        }
    }

    $props = mlvProps(mlvPage($reader, $this->community));
    expect($props['members']['pagination']['totalCount'])->toBe(5)
        ->and($props['hiddenFromList'])->toBeFalse();
})->with('mlv managers');

test('the hidden member gets their own entry with the marker and the line, and no other hidden member (#1466)', function () {
    $other = User::factory()->withUserProfile()->create(['name' => 'Ottoline Qzsecond', 'username' => 'qz-second-hid', 'show_in_member_list' => false]);
    $this->community->users()->attach($other, ['role' => KnowiiCommunityMemberRole::Member]);

    $api = mlvApi($this->hidden, $this->community)->assertOk();
    expect(mlvEntryOf($api->json('data'), $this->hidden)['in_member_list'])->toBeFalse()
        ->and(mlvEntryOf($api->json('data'), $other))->toBeNull()
        ->and($api->json('metadata.pagination.totalCount'))->toBe(5)
        ->and((string) $api->getContent())->not->toContain('qz-second-hid');

    expect(mlvApi($this->hidden, $this->community, ['query' => MLV_HIDDEN_NAME_START])->assertOk()->json('data.*.cuid'))->toBe([$this->hidden->cuid]);

    $props = mlvProps(mlvPage($this->hidden, $this->community));
    expect($props['hiddenFromList'])->toBeTrue()
        ->and(mlvEntryOf($props['members']['items'], $this->hidden)['in_member_list'])->toBeFalse()
        ->and(mlvEntryOf($props['members']['items'], $other))->toBeNull();

    // The other members are listed with true
    foreach ($props['members']['items'] as $entry) {
        if ($entry['cuid'] !== $this->hidden->cuid) {
            expect($entry['in_member_list'])->toBeTrue();
        }
    }
});

test('the team is always listed: an admin, a moderator without a right and the owner with the switch off (#1466)', function () {
    $member = $this->c['member'];
    $about = fn (Community $community): array => mlvProps(mlvActingAs($member)->get(route('communities.about', ['communitySlug' => $community->slug])))['team'];
    $setRole = fn (KnowiiCommunityMemberRole $role) => $this->community->users()->updateExistingPivot($this->hidden->id, ['role' => $role]);

    expect(mlvEntryOf($about($this->community), $this->hidden))->toBeNull();

    $setRole(KnowiiCommunityMemberRole::Admin);
    expect(mlvEntryOf(mlvApi($member, $this->community)->json('data'), $this->hidden)['in_member_list'])->toBeTrue()
        ->and(mlvEntryOf($about($this->community), $this->hidden))->not->toBeNull()
        ->and(mlvProps(mlvPage($this->hidden, $this->community))['hiddenFromList'])->toBeFalse();

    mlvModeratorsManageMembers($this->community, false);
    $setRole(KnowiiCommunityMemberRole::Moderator);
    expect(mlvEntryOf(mlvApi($member, $this->community)->json('data'), $this->hidden)['in_member_list'])->toBeTrue()
        ->and(mlvEntryOf($about($this->community), $this->hidden))->not->toBeNull();

    $owner = $this->c['owner'];
    $owner->forceFill(['show_in_member_list' => false])->save();
    expect(mlvEntryOf(mlvApi($member, $this->community)->json('data'), $owner)['in_member_list'])->toBeTrue()
        ->and(mlvEntryOf($about($this->community), $owner))->not->toBeNull();

    // An admin in a second community, a plain member in the first one: listed there, hidden here
    $setRole(KnowiiCommunityMemberRole::Member);
    $second = Community::factory()->create(['owner_id' => $owner->id]);
    $second->users()->attach($this->hidden, ['role' => KnowiiCommunityMemberRole::Admin]);
    $second->users()->attach($member, ['role' => KnowiiCommunityMemberRole::Member]);
    expect(mlvEntryOf(mlvApi($member, $this->community)->json('data'), $this->hidden))->toBeNull()
        ->and(mlvEntryOf(mlvApi($member, $second)->json('data'), $this->hidden)['in_member_list'])->toBeTrue();
});

test('the member page, a typed mention, the member count and the online list do not change (#1466)', function () {
    $member = $this->c['member'];

    mlvActingAs($member)->get(route('members.show', ['communitySlug' => $this->community->slug, 'username' => $this->hidden->username]))->assertOk();

    $group = SpaceGroup::factory()->create(['community_id' => $this->community->id]);
    $space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion]);
    $post = app(CreatesPosts::class)->create($member, $space, ['title' => 'Hello', 'body' => 'Thanks @'.MLV_HIDDEN_USERNAME]);
    $profile = route('members.show', ['communitySlug' => $this->community->slug, 'username' => MLV_HIDDEN_USERNAME], false);
    expect($post->body_html)->toContain('<a class="mention" href="'.$profile.'">@'.MLV_HIDDEN_USERNAME.'</a>')
        ->and($post->mentions()->pluck('user_id')->all())->toBe([$this->hidden->id])
        ->and(Notification::query()->where('user_id', $this->hidden->id)->where('kind', KnowiiNotificationKind::Mention)->count())->toBe(1);

    // The owner, the admin, the moderator, the member and the hidden member
    expect(mlvProps(mlvActingAs($member)->get(route('communities.about', ['communitySlug' => $this->community->slug])))['community']['member_count'])->toBe(5);

    // The online list follows the online status only (ADR 0027)
    app()->instance(ReadsPresenceChannels::class, new class([$this->hidden->cuid, $this->c['admin']->cuid]) implements ReadsPresenceChannels
    {
        /** @param list<string> $cuids */
        public function __construct(private array $cuids) {}

        public function memberCuids(string $channel): array
        {
            return $this->cuids;
        }
    });
    mlvActingAs($member)->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_ONLINE_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['cuid' => $this->hidden->cuid]);
});

test('25 listed members and 3 hidden ones: a member pages through the 25 with no hidden one and no double; a manager gets 28 (#1466)', function () {
    // 4 listed people already (the owner, the admin, the moderator, the member) and 1 hidden one
    for ($i = 1; $i <= 21; $i++) {
        $user = User::factory()->withUserProfile()->create(['username' => sprintf('qz-listed-%02d', $i), 'name' => sprintf('Listed Qzpage %02d', $i)]);
        $this->community->users()->attach($user, ['role' => KnowiiCommunityMemberRole::Member]);
    }
    $hidden = [$this->hidden->cuid];
    for ($i = 1; $i <= 2; $i++) {
        $user = User::factory()->withUserProfile()->create(['username' => sprintf('qz-hidden-%02d', $i), 'name' => sprintf('Hidden Qzpage %02d', $i), 'show_in_member_list' => false]);
        $this->community->users()->attach($user, ['role' => KnowiiCommunityMemberRole::Member]);
        $hidden[] = $user->cuid;
    }

    $member = $this->c['member'];
    $props = mlvProps(mlvPage($member, $this->community));
    $first = array_column($props['members']['items'], 'cuid');
    expect($first)->toHaveCount(20)
        ->and($props['members']['pagination']['nextOffset'])->toBe(20)
        ->and($props['members']['pagination']['totalCount'])->toBe(25);

    $api = mlvApi($member, $this->community, ['sort' => 'name', 'limit' => 20, 'offset' => 20])->assertOk();
    $second = $api->json('data.*.cuid');
    expect($second)->toHaveCount(5)
        ->and($api->json('metadata.pagination.nextOffset'))->toBeNull()
        ->and(array_intersect($first, $second))->toBe([])
        ->and(array_unique([...$first, ...$second]))->toHaveCount(25)
        ->and(array_intersect([...$first, ...$second], $hidden))->toBe([]);

    $all = mlvApi($this->c['admin'], $this->community, ['limit' => 100])->assertOk();
    expect($all->json('data'))->toHaveCount(28)
        ->and($all->json('metadata.pagination.totalCount'))->toBe(28)
        ->and(array_intersect($all->json('data.*.cuid'), $hidden))->toHaveCount(3);
});

test('the page and the API cost the same number of queries with 3 members and with 30, with hidden people, for a member and for a manager (#1466)', function (string $who) {
    $owner = User::factory()->withUserProfile()->create(['username' => 'qz-cost-owner', 'name' => 'Olive Qzcost']);
    $community = Community::factory()->create(['owner_id' => $owner->id]);
    $join = function (string $username, KnowiiCommunityMemberRole $role = KnowiiCommunityMemberRole::Member, bool $shown = true) use ($community): User {
        $user = User::factory()->withUserProfile()->create(['username' => $username, 'name' => 'Name '.$username, 'show_in_member_list' => $shown]);
        $community->users()->attach($user, ['role' => $role]);

        return $user;
    };
    $reader = $join('qz-cost-reader', $who === 'admin' ? KnowiiCommunityMemberRole::Admin : KnowiiCommunityMemberRole::Member);
    $join('qz-cost-hidden', shown: false);

    $page = fn () => mlvPage($reader, $community)->assertOk();
    $api = fn () => mlvApi($reader, $community, ['sort' => 'name', 'limit' => 100])->assertOk();

    // The first visit of a day writes the date of the visit of the account: it is not a query of the list
    $page();
    $pageWith3 = mlvQueryCount($page);
    $apiWith3 = mlvQueryCount($api);

    for ($i = 1; $i <= 27; $i++) {
        $join(sprintf('qz-cost-%02d', $i), shown: $i % 3 !== 0);
    }
    // 9 of the 27 new members are hidden, and the first hidden one: a member gets the 20 others
    $expected = $who === 'admin' ? 30 : 20;
    expect(mlvProps($page())['members']['pagination']['totalCount'])->toBe($expected)
        ->and($api()->json('data'))->toHaveCount($expected);

    expect(mlvQueryCount($page))->toBe($pageWith3)
        ->and(mlvQueryCount($api))->toBe($apiWith3);
})->with(['member', 'admin']);

test('the answer of a role change reads the list as the caller: a manager gets the hidden member, with in_member_list false (#1466)', function () {
    $this->community->users()->updateExistingPivot($this->hidden->id, ['role' => KnowiiCommunityMemberRole::Moderator]);
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBER, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_MEMBER => $this->hidden->cuid]);

    // Back to a plain member: hidden again, and still in the answer for the owner
    mlvActingAs($this->c['owner'])->patchJson($path, ['role' => 'member'])
        ->assertOk()
        ->assertJsonPath('data.cuid', $this->hidden->cuid)
        ->assertJsonPath('data.role', 'member')
        ->assertJsonPath('data.in_member_list', false);
});

test('a hidden moderator is on the team: listed, and the directory gives no line about hiding (#1466)', function () {
    $this->community->users()->updateExistingPivot($this->hidden->id, ['role' => KnowiiCommunityMemberRole::Moderator]);

    $props = mlvProps(mlvPage($this->hidden, $this->community));
    expect($props['hiddenFromList'])->toBeFalse()
        ->and(mlvEntryOf($props['members']['items'], $this->hidden)['in_member_list'])->toBeTrue();
});

test('a hidden owner with a membership row as a member is listed one time, as the owner, with in_member_list true (#1466)', function () {
    $owner = $this->c['owner'];
    $owner->forceFill(['show_in_member_list' => false])->save();
    $this->community->users()->attach($owner, ['role' => KnowiiCommunityMemberRole::Member]);

    $entries = mlvApi($this->c['member'], $this->community, ['limit' => 100])->assertOk()->json('data');
    $found = array_values(array_filter($entries, static fn (array $entry): bool => $entry['cuid'] === $owner->cuid));
    expect($found)->toHaveCount(1)
        ->and($found[0]['role'])->toBe('owner')
        ->and($found[0]['in_member_list'])->toBeTrue();
});

// ---------------------------------------------------------------------------------------------------------------------
// "Send a message" from a member page
// ---------------------------------------------------------------------------------------------------------------------

test('the Messages page with ?to= gives the recipient, also a hidden member, and null for anyone else (#1466)', function () {
    $member = $this->c['member'];

    $recipient = mlvMessagesProps($member, $this->community, ['to' => MLV_HIDDEN_USERNAME])['recipient'];
    expect($recipient)->toBeArray()
        ->and($recipient['cuid'])->toBe($this->hidden->cuid)
        ->and($recipient['username'])->toBe(MLV_HIDDEN_USERNAME)
        ->and($recipient['name'])->toBe(MLV_HIDDEN_NAME);
    $keys = array_keys($recipient);
    sort($keys);
    expect($keys)->toBe(['avatar_url', 'cuid', 'name', 'username'])
        ->and(json_encode($recipient))->not->toContain(MLV_HIDDEN_EMAIL)->not->toContain('@example.com');

    // Without the case
    expect(mlvMessagesProps($member, $this->community, ['to' => strtoupper(MLV_HIDDEN_USERNAME)])['recipient']['cuid'])->toBe($this->hidden->cuid);
    // The owner has no membership row
    expect(mlvMessagesProps($member, $this->community, ['to' => $this->c['owner']->username])['recipient']['cuid'])->toBe($this->c['owner']->cuid);

    foreach ([['to' => $member->username], ['to' => $this->c['outsider']->username], ['to' => 'qz-nobody-has-this'], ['to' => ['x']], ['to' => ''], []] as $query) {
        expect(mlvMessagesProps($member, $this->community, $query)['recipient'])->toBeNull();
    }
});

// ---------------------------------------------------------------------------------------------------------------------
// Personal data
// ---------------------------------------------------------------------------------------------------------------------

test('the export of the account holds the choice, and its read-me names it (#1466)', function () {
    app(UpdatesMemberListVisibility::class)->update($this->c['member'], ['show_in_member_list' => false]);

    $status = Artisan::call('users:export', ['email' => $this->c['member']->email]);
    expect($status)->toBe(0);
    $path = trim(Artisan::output());
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $account = json_decode((string) $zip->getFromName('account.json'), true);
    $readme = (string) $zip->getFromName('README.txt');
    $zip->close();

    expect($account['account']['show_in_member_list'])->toBeFalse()
        ->and($account['account']['show_online_status'])->toBeTrue()
        ->and($readme)->toContain('your choice to be in the member lists');
});

test('the request of a visit of the directory gets hiddenFromList, computed on the server (#1466)', function () {
    $response = mlvActingAs($this->hidden)->get(route('members.index', ['communitySlug' => $this->community->slug]), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Members/Index',
        'X-Inertia-Partial-Data' => 'hiddenFromList',
    ]);
    $response->assertOk()->assertJsonPath('props.hiddenFromList', true);
});
