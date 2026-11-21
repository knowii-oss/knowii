<?php

use App\ApiRoutes;
use App\Contracts\Users\DeletesAccounts;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiEventLocationVisibility;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceContentChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\Event as EventModel;
use App\Models\EventAnswer;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\CommunityRoles;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
 * "My events" (#1515, ADR 0053, "The calendar file and My events"): `GET user/events`, the events that the member
 * answered Yes or Replay only and the events that they host, in all their communities, and only in the spaces that they
 * can read today. Mia is a member of two communities. The names, the titles and the times are fixed.
 */

const UEV_MEETING_URL = 'https://meet.example.com/room-of-my-events';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10T09:00:00Z'));
    Event::fake([SpaceTreeChanged::class, SpaceContentChanged::class]);

    $person = fn (string $name, string $username) => User::factory()->withUserProfile()->create(['name' => $name, 'username' => $username, 'email' => $username.'@example.com']);
    $this->people = [
        'owner' => $person('Olga Owner', 'olga'),
        'admin' => $person('Adam Admin', 'adam'),
        'moderator' => $person('Mona Moderator', 'mona'),
        'host' => $person('Hester Host', 'hester'),
        'mia' => $person('Mia Member', 'mia'),
        'other' => $person('Oscar Other', 'oscar'),
        'outsider' => $person('Otto Outsider', 'otto'),
    ];
    $this->first = Community::factory()->create(['owner_id' => $this->people['owner']->id, 'name' => 'Example community']);
    $this->second = Community::factory()->create(['owner_id' => $this->people['owner']->id, 'name' => 'Second community']);
    // A community of which Mia is not a member
    $this->third = Community::factory()->create(['owner_id' => $this->people['other']->id, 'name' => 'Third community']);
    foreach ([$this->first, $this->second] as $community) {
        $community->users()->attach($this->people['admin'], ['role' => KnowiiCommunityMemberRole::Admin]);
        $community->users()->attach($this->people['moderator'], ['role' => KnowiiCommunityMemberRole::Moderator]);
        foreach (['host', 'mia', 'other'] as $key) {
            $community->users()->attach($this->people[$key], ['role' => KnowiiCommunityMemberRole::Member]);
        }
    }

    $space = function (Community $community, string $name, KnowiiSpaceVisibility $visibility): Space {
        $group = SpaceGroup::factory()->create(['community_id' => $community->id, 'name' => 'Group of '.$name]);

        return Space::factory()->inGroup($group)->create(['name' => $name, 'type' => KnowiiSpaceType::Events, 'visibility' => $visibility, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    };
    $this->office = $space($this->first, 'Office hours', KnowiiSpaceVisibility::Public);
    $this->buyerHours = $space($this->first, 'Buyer hours', KnowiiSpaceVisibility::Groups);
    $this->study = $space($this->second, 'Study hours', KnowiiSpaceVisibility::Members);
    $this->elsewhere = $space($this->third, 'Open hours', KnowiiSpaceVisibility::Public);
    $this->buyers = AccessGroup::query()->create(['community_id' => $this->first->id, 'name' => 'Buyers']);
    $this->buyerHours->accessGroups()->attach($this->buyers);
    $this->buyers->members()->attach([$this->people['mia']->id]);

    $event = function (Space $space, string $title, string $start, array $more = [], ?User $host = null): EventModel {
        return EventModel::factory()->inSpace($space)->hostedBy($host ?? $this->people['host'])->create([
            'title' => $title,
            'starts_at' => CarbonImmutable::parse($start),
            'ends_at' => CarbonImmutable::parse($start)->addHour(),
            'meeting_url' => UEV_MEETING_URL,
            ...$more,
        ]);
    };
    $answer = fn (EventModel $event, string $key, string $value) => EventAnswer::factory()->to($event)->of($this->people[$key])->state(['answer' => $value])->create();

    $mia = $this->people['mia'];
    $this->e = [
        'yes' => $event($this->office, 'Weekly review', '2026-11-18T16:00:00Z', ['location_visibility' => KnowiiEventLocationVisibility::Going, 'capacity' => 40]),
        'secondYes' => $event($this->study, 'Study group', '2026-11-19T12:00:00Z'),
        'replay' => $event($this->office, 'Replay session', '2026-11-19T16:00:00Z', ['replay_expected' => true]),
        'no' => $event($this->office, 'Declined talk', '2026-11-20T16:00:00Z'),
        'unanswered' => $event($this->office, 'Unanswered meetup', '2026-11-21T16:00:00Z'),
        'hosted' => $event($this->office, 'Mia hosts', '2026-11-22T16:00:00Z', [], $mia),
        'ownDraft' => $event($this->office, 'Mia draft', '2026-11-23T16:00:00Z', ['state' => 'draft'], $mia),
        'otherDraft' => $event($this->office, 'Other draft', '2026-11-24T16:00:00Z', ['state' => 'draft']),
        'cancelledYes' => $event($this->office, 'Cancelled with a Yes', '2026-11-25T16:00:00Z', ['state' => 'cancelled']),
        'hostedAndYes' => $event($this->office, 'Mia hosts and goes', '2026-11-26T16:00:00Z', [], $mia),
        'groupsYes' => $event($this->buyerHours, 'Buyer workshop', '2026-11-27T16:00:00Z'),
        'secondHosted' => $event($this->study, 'Mia hosts a study', '2026-11-28T16:00:00Z', [], $mia),
        'pastYes' => $event($this->office, 'Last week review', '2026-11-03T16:00:00Z'),
        'otherYes' => $event($this->study, 'Oscar goes', '2026-11-18T10:00:00Z'),
        'elsewhere' => $event($this->elsewhere, 'Open to everyone', '2026-11-18T09:00:00Z', [], $this->people['other']),
    ];
    foreach (['yes', 'secondYes', 'cancelledYes', 'hostedAndYes', 'groupsYes', 'pastYes'] as $key) {
        $answer($this->e[$key], 'mia', 'yes');
    }
    $answer($this->e['replay'], 'mia', 'replay');
    $answer($this->e['no'], 'mia', 'no');
    $answer($this->e['otherYes'], 'other', 'yes');
    // A row of a person who is not a member of the community: it never gives an event
    $answer($this->e['elsewhere'], 'mia', 'yes');
});

function uevUser(string $key): User
{
    // A new instance: the one of the test keeps the relations that an earlier request loaded
    return User::query()->findOrFail(test()->people[$key]->id);
}

function uevCall(string $reader, string $query = ''): TestResponse
{
    if ($reader === 'visitor') {
        app('auth')->forgetGuards();

        return test()->getJson(ApiRoutes::path(ApiRoutes::USER_EVENTS).$query);
    }

    return test()->actingAs(uevUser($reader))->getJson(ApiRoutes::path(ApiRoutes::USER_EVENTS).$query);
}

/**
 * The titles of the list of a reader
 *
 * @return list<string>
 */
function uevTitles(string $reader, string $query = ''): array
{
    return array_column((array) uevCall($reader, $query.(str_contains($query, '?') ? '&' : '?').'limit=100')->assertOk()->json('data'), 'title');
}

// ---------------------------------------------------------------------------------------------------------------------
// The two ways in, the filters and the order (rules 36 to 45)
// ---------------------------------------------------------------------------------------------------------------------

test('the upcoming list holds the own drafts first, then the next start first: the Yes and the Replay only answers and the hosted events of both communities', function () {
    expect(uevTitles('mia'))->toBe([
        'Mia draft',
        'Weekly review',
        'Study group',
        'Replay session',
        'Mia hosts',
        'Cancelled with a Yes',
        'Mia hosts and goes',
        'Buyer workshop',
        'Mia hosts a study',
    ]);
});

test('"past" holds the events that ended, the newest start first, and each event is in exactly one of the two lists', function () {
    expect(uevTitles('mia', '?scope=past'))->toBe(['Last week review']);

    $this->travelTo(CarbonImmutable::parse('2026-11-19T12:30:00Z'));
    $upcoming = uevTitles('mia');
    $past = uevTitles('mia', '?scope=past');
    expect($past)->toBe(['Weekly review', 'Last week review'])
        // "Study group" happens now: it is upcoming until its end
        ->and($upcoming[1])->toBe('Study group')
        ->and(array_intersect($upcoming, $past))->toBe([]);
});

test('each value of answer gives its events only', function (string $answer, array $titles) {
    expect(uevTitles('mia', '?answer='.$answer))->toBe($titles);
})->with([
    'yes' => ['yes', ['Weekly review', 'Study group', 'Cancelled with a Yes', 'Mia hosts and goes', 'Buyer workshop']],
    'replay' => ['replay', ['Replay session']],
    'host' => ['host', ['Mia draft', 'Mia hosts', 'Mia hosts and goes', 'Mia hosts a study']],
]);

test('a wrong scope or a wrong answer is a field error', function () {
    uevCall('mia', '?scope=soon')->assertStatus(400)->assertJsonPath('errors.scope.0', 'Use upcoming or past');
    uevCall('mia', '?answer=no')->assertStatus(400)->assertJsonPath('errors.answer.0', 'Use yes, replay or host');
    uevCall('mia', '?answer=maybe')->assertStatus(400)->assertJsonPath('errors.answer.0', 'Use yes, replay or host');
});

test('the list is paginated with limit and offset', function () {
    $page = uevCall('mia', '?limit=2&offset=2')->assertOk();

    expect(array_column((array) $page->json('data'), 'title'))->toBe(['Study group', 'Replay session'])
        ->and($page->json('metadata.pagination'))->toBe(['limit' => 2, 'offset' => 2, 'previousOffset' => 0, 'nextOffset' => 4, 'currentPage' => 2, 'pageCount' => 5, 'totalCount' => 9])
        ->and($page->json('message'))->toBe('My events');
    // The default limit of the API
    expect(uevCall('mia')->json('metadata.pagination.limit'))->toBe(10);
});

test('an event that the member hosts and answered is one item, and every item carries its space and its community', function () {
    $items = (array) uevCall('mia', '?limit=100')->assertOk()->json('data');
    $byTitle = array_column($items, null, 'title');

    expect(array_count_values(array_column($items, 'cuid')))->each->toBe(1)
        ->and($byTitle['Mia hosts and goes']['my_answer'])->toBe('yes')
        ->and($byTitle['Weekly review']['community'])->toBe(['cuid' => $this->first->cuid, 'slug' => $this->first->slug, 'name' => 'Example community'])
        ->and($byTitle['Weekly review']['space'])->toBe(['cuid' => $this->office->cuid, 'slug' => $this->office->slug, 'name' => 'Office hours'])
        ->and($byTitle['Study group']['community'])->toBe(['cuid' => $this->second->cuid, 'slug' => $this->second->slug, 'name' => 'Second community'])
        // No description in a list
        ->and(array_unique(array_column($items, 'body')))->toBe([null]);
});

test('each item equals the event of GET .../events/{event} for the same reader, field by field, without the description', function () {
    $items = (array) uevCall('mia', '?limit=100')->assertOk()->json('data');
    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        $model = EventModel::query()->where('cuid', $item['cuid'])->with('space', 'community')->firstOrFail();
        $one = (array) test()->actingAs(uevUser('mia'))->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENT, [
            ApiRoutes::PARAM_COMMUNITY => $model->community->cuid, ApiRoutes::PARAM_SPACE => $model->space->cuid, ApiRoutes::PARAM_EVENT => $model->cuid,
        ]))->assertOk()->json('data');
        unset($one['body'], $one['body_html'], $item['body'], $item['body_html']);

        expect($item)->toBe($one);
    }
});

test('the meeting address of a "going" event is in an item only for a reader who may see it', function () {
    $byTitle = fn (string $reader) => array_column((array) uevCall($reader, '?limit=100')->assertOk()->json('data'), null, 'title');

    expect($byTitle('mia')['Weekly review']['meeting_url'])->toBe(UEV_MEETING_URL);
    EventAnswer::query()->where('event_id', $this->e['yes']->id)->where('user_id', $this->people['mia']->id)->update(['answer' => 'replay']);
    expect($byTitle('mia')['Weekly review']['meeting_url'])->toBeNull()
        ->and($byTitle('mia')['Weekly review']['can_view_location'])->toBeFalse();
});

// ---------------------------------------------------------------------------------------------------------------------
// Who gets which events (rules 39 to 42 and 73 to 77)
// ---------------------------------------------------------------------------------------------------------------------

test('never a draft of another host, also for the owner and the admins, never a No, never an event without an answer', function () {
    EventAnswer::factory()->to($this->e['otherDraft'])->of($this->people['owner'])->yes()->create();

    expect(uevTitles('mia'))->not->toContain('Other draft')->not->toContain('Declined talk')->not->toContain('Unanswered meetup')
        ->and(uevTitles('owner'))->toBe([])
        ->and(uevTitles('admin'))->toBe([])
        // The host of the draft has it
        ->and(uevTitles('host', '?answer=host'))->toContain('Other draft');
});

test('a member who left a community gets no event of that community, as an answer and as a host, and gets them again when they come back', function () {
    $this->second->removeUser(uevUser('mia'));

    expect(uevTitles('mia'))->not->toContain('Study group')->not->toContain('Mia hosts a study')
        ->and(uevTitles('mia'))->toContain('Weekly review');

    // Back as a member: the answers left with the member (Community::removeUser), the hosted event is there again
    $this->second->users()->attach($this->people['mia'], ['role' => KnowiiCommunityMemberRole::Member]);
    expect(uevTitles('mia'))->toContain('Mia hosts a study')->not->toContain('Study group');
});

test('a member who lost the access group of a "groups" space gets none of its events, and the answer stays for the day the access comes back', function () {
    $this->buyers->members()->detach($this->people['mia']->id);

    expect(uevTitles('mia'))->not->toContain('Buyer workshop')
        ->and(EventAnswer::query()->where('event_id', $this->e['groupsYes']->id)->where('user_id', $this->people['mia']->id)->exists())->toBeTrue();

    $this->buyers->members()->attach($this->people['mia']->id);
    expect(uevTitles('mia'))->toContain('Buyer workshop');
});

test('an event of a public space of a community of which the reader is not a member is never in the list', function () {
    expect(uevTitles('mia'))->not->toContain('Open to everyone')
        ->and(uevTitles('outsider'))->toBe([]);
});

test('the event of a deleted host stays in the list of a member who answered Yes, without a host, and is in the list of nobody as a hosted event', function () {
    app(DeletesAccounts::class)->delete(uevUser('host'));

    $items = array_column((array) uevCall('mia', '?limit=100')->assertOk()->json('data'), null, 'title');
    expect($items['Weekly review']['host'])->toBeNull()
        ->and(EventModel::query()->whereKey($this->e['yes']->id)->value('host_id'))->toBeNull();
    foreach (['owner', 'admin', 'moderator'] as $reader) {
        expect(uevTitles($reader, '?answer=host'))->toBe([]);
    }
    expect(uevTitles('other', '?answer=host'))->toBe(['Open to everyone']);
});

test('a member of two communities gets the events of both, and never an event of a community of another member', function () {
    $items = (array) uevCall('mia', '?limit=100')->assertOk()->json('data');

    expect(array_values(array_unique(array_column(array_column($items, 'community'), 'name'))))->toEqualCanonicalizing(['Example community', 'Second community'])
        ->and(array_column($items, 'title'))->not->toContain('Oscar goes')
        // Oscar answered in the second community, and hosts in the third, which he owns
        ->and(uevTitles('other'))->toBe(['Open to everyone', 'Oscar goes']);
});

// ---------------------------------------------------------------------------------------------------------------------
// Access, tokens and queries (rules 43, 46 and 78)
// ---------------------------------------------------------------------------------------------------------------------

test('a visitor gets 401, a token without events:read 403, and a token without event-answers:read no limit of places', function () {
    uevCall('visitor')->assertUnauthorized();

    Sanctum::actingAs(uevUser('mia'), ['posts:read']);
    $this->getJson(ApiRoutes::path(ApiRoutes::USER_EVENTS))->assertForbidden();

    // Mia hosts an event with a limit: a session reads the limit, a token without event-answers:read does not
    $this->e['hosted']->update(['capacity' => 12]);
    expect(array_column((array) uevCall('mia', '?answer=host')->json('data'), 'capacity', 'title')['Mia hosts'])->toBe(12);
    Sanctum::actingAs(uevUser('mia'), ['events:read']);
    $items = array_column((array) $this->getJson(ApiRoutes::path(ApiRoutes::USER_EVENTS).'?answer=host')->assertOk()->json('data'), null, 'title');
    expect($items['Mia hosts']['capacity'])->toBeNull()
        ->and($items['Mia hosts']['places_left'])->toBe(12);
});

test('the database and the policy of the space agree on who reads the events of a space, for each visibility', function (string $visibility, string $groupOn, array $expected) {
    $group = SpaceGroup::factory()->create(['community_id' => $this->first->id, 'name' => 'Group of the twin']);
    $space = Space::factory()->inGroup($group)->create(['name' => 'Twin hours', 'type' => KnowiiSpaceType::Events, 'visibility' => $visibility]);
    $twin = AccessGroup::query()->create(['community_id' => $this->first->id, 'name' => 'Twins']);
    $others = AccessGroup::query()->create(['community_id' => $this->first->id, 'name' => 'Others']);
    match ($groupOn) {
        'the space' => $space->accessGroups()->attach($twin),
        'the space group' => $group->accessGroups()->attach($twin),
        default => null,
    };
    // A group that opens the space (Mia, the moderator and a person who is not a member), and a group that opens nothing
    $twin->members()->attach([$this->people['mia']->id, $this->people['moderator']->id, $this->people['outsider']->id]);
    $others->members()->attach([$this->people['other']->id]);
    $event = EventModel::factory()->inSpace($space)->hostedBy($this->people['host'])->create(['title' => 'Twin event']);

    $byDatabase = [];
    $byPolicy = [];
    foreach ($this->people as $key => $user) {
        $fresh = User::query()->findOrFail($user->id);
        if (EventModel::query()->readableBy($fresh)->whereKey($event->id)->exists()) {
            $byDatabase[] = $key;
        }
        $space = Space::query()->findOrFail($space->id);
        if ($fresh->belongsToCommunity($this->first) && Gate::forUser($fresh)->check('view', $space)) {
            $byPolicy[] = $key;
        }
    }

    expect($byDatabase)->toBe($byPolicy)->toBe($expected);
})->with([
    'a public space' => ['public', 'nothing', ['owner', 'admin', 'moderator', 'host', 'mia', 'other']],
    'a members space' => ['members', 'nothing', ['owner', 'admin', 'moderator', 'host', 'mia', 'other']],
    'a groups space, the group on the space' => ['groups', 'the space', ['owner', 'admin', 'moderator', 'mia']],
    'a groups space, the group on the space group' => ['groups', 'the space group', ['owner', 'admin', 'moderator', 'mia']],
    'a groups space that no group opens' => ['groups', 'nothing', ['owner', 'admin']],
]);

test('a page of 5 events and a page of 20 events of the same spaces run the same number of queries', function () {
    foreach (range(1, 24) as $i) {
        $space = $i % 2 === 0 ? $this->office : $this->study;
        $event = EventModel::factory()->inSpace($space)->hostedBy($this->people['host'])->create([
            // Past events of two spaces: both pages hold events of the same two spaces and communities
            'title' => "Extra event {$i}", 'starts_at' => CarbonImmutable::parse('2026-11-01T10:00:00Z')->addHours($i), 'ends_at' => CarbonImmutable::parse('2026-11-01T11:00:00Z')->addHours($i),
            'capacity' => $i % 3 === 0 ? 50 : null,
        ]);
        EventAnswer::factory()->to($event)->of($this->people['mia'])->state(['answer' => $i % 4 === 0 ? 'replay' : 'yes'])->create();
    }
    $queries = function (string $query): int {
        $mia = uevUser('mia');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($mia)->getJson(ApiRoutes::path(ApiRoutes::USER_EVENTS).$query)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    // A first request fills what the application keeps for the next ones
    $queries('?scope=past&limit=5');
    $few = $queries('?scope=past&limit=5');
    $many = $queries('?scope=past&limit=20');

    expect($many)->toBe($few);
});

test('a page with the events of 2 communities and a page with the events of 6 communities run the same number of queries', function () {
    // Two new members: Nina is a member of 2 communities, Theo of 6. Each community has one events space with one past
    // event that the member answered Yes
    $nina = User::factory()->withUserProfile()->create(['name' => 'Nina Member', 'username' => 'nina', 'email' => 'nina@example.com']);
    $theo = User::factory()->withUserProfile()->create(['name' => 'Theo Member', 'username' => 'theo', 'email' => 'theo@example.com']);
    foreach (range(1, 6) as $i) {
        $community = Community::factory()->create(['owner_id' => $this->people['owner']->id, 'name' => "Count community {$i}"]);
        $community->users()->attach($theo, ['role' => KnowiiCommunityMemberRole::Member]);
        if ($i <= 2) {
            $community->users()->attach($nina, ['role' => KnowiiCommunityMemberRole::Member]);
        }
        $group = SpaceGroup::factory()->create(['community_id' => $community->id, 'name' => "Count group {$i}"]);
        $space = Space::factory()->inGroup($group)->create(['name' => "Count hours {$i}", 'type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Members, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
        $event = EventModel::factory()->inSpace($space)->hostedBy($this->people['owner'])->create([
            'title' => "Count event {$i}", 'starts_at' => CarbonImmutable::parse('2026-11-02T10:00:00Z')->addHours($i), 'ends_at' => CarbonImmutable::parse('2026-11-02T11:00:00Z')->addHours($i),
        ]);
        foreach ($i <= 2 ? [$nina, $theo] : [$theo] as $member) {
            EventAnswer::factory()->to($event)->of($member)->yes()->create();
        }
    }
    $queries = function (User $member, int $count): int {
        $member = User::query()->findOrFail($member->id);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($member)->getJson(ApiRoutes::path(ApiRoutes::USER_EVENTS).'?scope=past')->assertOk();
        DB::disableQueryLog();
        $names = array_column(array_column((array) $response->json('data'), 'community'), 'name');
        expect(array_unique($names))->toHaveCount($count);

        return count(DB::getQueryLog());
    };

    // A first request fills what the application keeps for the next ones
    $queries($nina, 2);
    $two = $queries($nina, 2);
    $six = $queries($theo, 6);

    expect($six)->toBe($two);
});

test('the admin status of a member in several communities is read with one query, and forgotten after the check', function () {
    $admin = uevUser('admin');
    $mia = uevUser('mia');
    $reads = 0;
    DB::listen(function ($query) use (&$reads): void {
        if (str_contains($query->sql, 'community_members')) {
            $reads++;
        }
    });

    [$first, $second, $read] = CommunityRoles::withKnownAdminsIn([$this->first, $this->second], [$admin->id, $mia->id], function () use ($admin, $mia, &$reads): array {
        $known = $reads;

        return [
            [CommunityRoles::isOwnerOrAdmin($admin, $this->first), CommunityRoles::isOwnerOrAdmin($mia, $this->first)],
            // A nested check for the same members asks nothing more
            CommunityRoles::withKnownAdmins($this->second, [$admin->id], fn (): array => [CommunityRoles::isOwnerOrAdmin($admin, $this->second), CommunityRoles::isOwnerOrAdmin($mia, $this->second)]),
            $known,
        ];
    });

    expect($read)->toBe(1)
        ->and($first)->toBe([true, false])
        ->and($second)->toBe([true, false]);
    // After the check the rule reads the database again
    $this->first->users()->updateExistingPivot($admin->id, ['role' => KnowiiCommunityMemberRole::Member]);
    expect(CommunityRoles::isOwnerOrAdmin(uevUser('admin'), $this->first))->toBeFalse()
        ->and(CommunityRoles::isOwnerOrAdmin(uevUser('admin'), $this->second))->toBeTrue();
});
