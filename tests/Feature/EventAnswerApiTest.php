<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiEventAnswer;
use App\Enums\KnowiiEventLocationVisibility;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceContentChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\Event as EventModel;
use App\Models\EventAnswer;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\EventAnswers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Jetstream\Jetstream;
use Laravel\Sanctum\Sanctum;

/*
 * The API of the answers to events (#1515, ADR 0053, "The answers and the places"): the four routes, the order of the
 * refusals, the meeting address of a "going" event for the members who answered Yes, and what no reader ever gets of
 * the answers of the others.
 */

const EAA_MEETING_URL = 'https://meet.example.com/room-of-the-yes-answers';

const EAA_PLACE = 'Attic of the old mill';

// A limit that no other number of the answers looks like
const EAA_CAPACITY = 4321;

const EAA_ANSWERERS = ['going' => 'Gwen Goingmember', 'replayer' => 'Rita Replaymember', 'refuser' => 'Nils Nomember'];

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10T09:00:00Z'));
    Event::fake([SpaceTreeChanged::class, SpaceContentChanged::class]);
    RateLimiter::clear('event-answers');
    $this->c = communityWithMembers();
    // The host and the members who answer are plain members: no right comes from a role
    foreach (['host' => 'Hester Host', ...EAA_ANSWERERS] as $key => $name) {
        $this->c[$key] = User::factory()->withUserProfile()->create(['name' => $name]);
        $this->c['community']->users()->attach($this->c[$key], ['role' => KnowiiCommunityMemberRole::Member]);
    }

    $this->group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $make = fn (KnowiiSpaceVisibility $visibility) => Space::factory()->inGroup($this->group)
        ->create(['type' => KnowiiSpaceType::Events, 'visibility' => $visibility, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    $this->space = $make(KnowiiSpaceVisibility::Public);
    $this->membersSpace = $make(KnowiiSpaceVisibility::Members);

    // A "going" event with a limit and a replay, in a public space: Gwen said Yes, Rita Replay only, Nils No
    $this->event = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create([
        'title' => 'Going only', 'meeting_url' => EAA_MEETING_URL, 'place' => EAA_PLACE,
        'location_visibility' => KnowiiEventLocationVisibility::Going, 'capacity' => EAA_CAPACITY, 'replay_expected' => true,
    ]);
    EventAnswer::factory()->to($this->event)->of($this->c['going'])->yes()->create();
    EventAnswer::factory()->to($this->event)->of($this->c['replayer'])->replay()->create();
    EventAnswer::factory()->to($this->event)->of($this->c['refuser'])->no()->create();
    // An event without a limit and without a replay
    $this->open = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create(['title' => 'Open to all']);
    $this->draft = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->draft()->create(['title' => 'A draft']);
});

function eaaPath(string $route, EventModel $event, ?Space $space = null): string
{
    $space ??= $event->space;

    return ApiRoutes::path($route, [
        ApiRoutes::PARAM_COMMUNITY => test()->c['community']->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        ApiRoutes::PARAM_EVENT => $event->cuid,
    ]);
}

function eaaAnswer(EventModel $event): string
{
    return eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_ANSWER, $event);
}

function eaaAnswers(EventModel $event, string $query = ''): string
{
    return eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_ANSWERS, $event).$query;
}

function eaaEvents(Space $space, string $query = ''): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENTS, [ApiRoutes::PARAM_COMMUNITY => test()->c['community']->cuid, ApiRoutes::PARAM_SPACE => $space->cuid]).$query;
}

function eaaCommunityEvents(): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_EVENTS, [ApiRoutes::PARAM_COMMUNITY => test()->c['community']->cuid]);
}

/**
 * A request of a reader: a key of the community of the test, a user, or "visitor"
 *
 * @param  array<string, mixed>  $body
 */
function eaaCall(string|User $reader, string $method, string $url, array $body = []): TestResponse
{
    $test = test();
    if ($reader === 'visitor') {
        // Nobody is signed in, also after an earlier request of the test as someone else
        app('auth')->forgetGuards();

        return $test->json($method, $url, $body);
    }

    return $test->actingAs($reader instanceof User ? $reader : $test->c[$reader])->json($method, $url, $body);
}

/**
 * The three reads of the API that carry the event: itself, the list of its space and the list of its community
 *
 * @return array<string, array<string, mixed>|null>
 */
function eaaEventOnEveryRead(string|User $reader, EventModel $event): array
{
    $one = eaaCall($reader, 'GET', eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $event))->assertOk()->json('data');
    $space = array_column((array) eaaCall($reader, 'GET', eaaEvents($event->space, '?limit=100'))->assertOk()->json('data'), null, 'cuid');
    $community = array_column((array) eaaCall($reader, 'GET', eaaCommunityEvents().'?limit=100')->assertOk()->json('data'), null, 'cuid');

    return ['one event' => $one, 'the list of the space' => $space[$event->cuid] ?? null, 'the list of the community' => $community[$event->cuid] ?? null];
}

// ---------------------------------------------------------------------------------------------------------------------
// The answer of a member
// ---------------------------------------------------------------------------------------------------------------------

test('a member sets, reads, changes and clears their answer through the API', function () {
    $url = eaaAnswer($this->open);

    eaaCall('member', 'GET', $url)->assertOk()->assertJsonPath('data.answer', null)->assertJsonPath('data.updated_at', null);

    $set = eaaCall('member', 'PUT', $url, ['answer' => 'yes'])->assertOk()->assertJsonPath('data.answer', 'yes')->assertJsonPath('message', 'Your answer');
    expect(array_keys((array) $set->json('data')))->toBe(['answer', 'updated_at'])
        ->and($set->json('data.updated_at'))->toBe('2026-11-10T09:00:00.000000Z');
    eaaCall('member', 'GET', $url)->assertOk()->assertJsonPath('data.answer', 'yes');

    // PUT replaces the one answer of the member (ADR 0011)
    $this->travel(5)->minutes();
    eaaCall('member', 'PUT', $url, ['answer' => 'no'])->assertOk()->assertJsonPath('data.answer', 'no')->assertJsonPath('data.updated_at', '2026-11-10T09:05:00.000000Z');
    expect(EventAnswer::query()->where('event_id', $this->open->id)->where('user_id', $this->c['member']->id)->count())->toBe(1);

    eaaCall('member', 'DELETE', $url)->assertNoContent();
    eaaCall('member', 'GET', $url)->assertOk()->assertJsonPath('data.answer', null);
    // Also when no row exists
    eaaCall('member', 'DELETE', $url)->assertNoContent();
});

test('the owner, an admin, a moderator and the host answer like members', function (string $role) {
    eaaCall($role, 'PUT', eaaAnswer($this->open), ['answer' => 'yes'])->assertOk()->assertJsonPath('data.answer', 'yes');
    eaaCall($role, 'GET', eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->open))->assertOk()->assertJsonPath('data.my_answer', 'yes')->assertJsonPath('data.can_answer', true);
})->with(['owner', 'admin', 'moderator', 'host']);

test('"Replay only" is an answer only when the host says that a replay will exist', function () {
    eaaCall('member', 'PUT', eaaAnswer($this->event), ['answer' => 'replay'])->assertOk()->assertJsonPath('data.answer', 'replay');
    eaaCall('member', 'PUT', eaaAnswer($this->open), ['answer' => 'replay'])->assertStatus(400)->assertJsonPath('errors.answer.0', 'This event has no replay.');
    expect(EventAnswer::query()->where('event_id', $this->open->id)->exists())->toBeFalse();
});

test('a new Yes on a full event answers 422 with the reason, and the same Yes again answers 200', function () {
    $this->event->update(['capacity' => 1]);

    eaaCall('member', 'PUT', eaaAnswer($this->event), ['answer' => 'yes'])->assertStatus(422)->assertJsonPath('message', 'This event is full.');
    eaaCall('replayer', 'PUT', eaaAnswer($this->event), ['answer' => 'yes'])->assertStatus(422)->assertJsonPath('message', 'This event is full.');
    eaaCall('going', 'PUT', eaaAnswer($this->event), ['answer' => 'yes'])->assertOk()->assertJsonPath('data.answer', 'yes');
    eaaCall('member', 'PUT', eaaAnswer($this->event), ['answer' => 'no'])->assertOk();
});

// ---------------------------------------------------------------------------------------------------------------------
// The refusals, in their order (rule 10)
// ---------------------------------------------------------------------------------------------------------------------

test('a draft that the person cannot see gives the body of an event that does not exist, on the four routes', function () {
    $missing = EventModel::factory()->inSpace($this->space)->make(['cuid' => 'nothing-here']);
    $missing->setRelation('space', $this->space);
    $calls = [
        ['GET', ApiRoutes::COMMUNITY_SPACE_EVENT_ANSWER, []],
        ['PUT', ApiRoutes::COMMUNITY_SPACE_EVENT_ANSWER, ['answer' => 'yes']],
        ['DELETE', ApiRoutes::COMMUNITY_SPACE_EVENT_ANSWER, []],
        ['GET', ApiRoutes::COMMUNITY_SPACE_EVENT_ANSWERS, []],
    ];

    // A member, a moderator (the draft has another host) and a person who is not a member
    foreach (['member', 'moderator', 'outsider'] as $reader) {
        foreach ($calls as [$method, $route, $body]) {
            $ofMissing = eaaCall($reader, $method, eaaPath($route, $missing), $body)->assertNotFound();
            $ofDraft = eaaCall($reader, $method, eaaPath($route, $this->draft), $body)->assertNotFound();

            expect($ofDraft->getContent())->toBe($ofMissing->getContent(), "{$reader} {$method} {$route}");
        }
    }
    expect(EventAnswer::query()->where('event_id', $this->draft->id)->exists())->toBeFalse();
});

test('an event of a space that the person cannot read answers 404 on the four routes', function () {
    $hidden = EventModel::factory()->inSpace($this->membersSpace)->hostedBy($this->c['host'])->create();
    $groups = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $buyers = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Buyers']);
    $groups->accessGroups()->attach($buyers);
    $forBuyers = EventModel::factory()->inSpace($groups)->hostedBy($this->c['owner'])->create();

    foreach ([['outsider', $hidden], ['member', $forBuyers], ['moderator', $forBuyers]] as [$reader, $event]) {
        eaaCall($reader, 'GET', eaaAnswer($event))->assertNotFound();
        eaaCall($reader, 'PUT', eaaAnswer($event), ['answer' => 'yes'])->assertNotFound();
        eaaCall($reader, 'DELETE', eaaAnswer($event))->assertNotFound();
        eaaCall($reader, 'GET', eaaAnswers($event))->assertNotFound();
    }
    expect(EventAnswer::query()->whereIn('event_id', [$hidden->id, $forBuyers->id])->exists())->toBeFalse();

    // An event addressed through another space of the community does not exist there
    foreach ([[ApiRoutes::COMMUNITY_SPACE_EVENT_ANSWER, 'GET'], [ApiRoutes::COMMUNITY_SPACE_EVENT_ANSWER, 'PUT'], [ApiRoutes::COMMUNITY_SPACE_EVENT_ANSWER, 'DELETE'], [ApiRoutes::COMMUNITY_SPACE_EVENT_ANSWERS, 'GET']] as [$route, $method]) {
        eaaCall('owner', $method, eaaPath($route, $this->event, $this->membersSpace), ['answer' => 'yes'])->assertNotFound();
    }
    expect(EventAnswer::query()->where('user_id', $this->c['owner']->id)->exists())->toBeFalse();
});

test('a person who is not a member never answers, also in a public space: 403 before anything else', function () {
    $cancelled = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->cancelled()->create();

    foreach ([$this->open, $cancelled] as $event) {
        eaaCall('outsider', 'GET', eaaAnswer($event))->assertForbidden();
        // 403 comes before the state of the event (422) and before the field (400)
        eaaCall('outsider', 'PUT', eaaAnswer($event), ['answer' => 'maybe'])->assertForbidden();
        eaaCall('outsider', 'DELETE', eaaAnswer($event))->assertForbidden();
        eaaCall('outsider', 'GET', eaaAnswers($event))->assertForbidden();
    }
    expect(EventAnswer::query()->where('user_id', $this->c['outsider']->id)->exists())->toBeFalse();
});

test('a visitor gets no route of the answers', function () {
    eaaCall('visitor', 'GET', eaaAnswer($this->open))->assertUnauthorized();
    eaaCall('visitor', 'PUT', eaaAnswer($this->open), ['answer' => 'yes'])->assertUnauthorized();
    eaaCall('visitor', 'DELETE', eaaAnswer($this->open))->assertUnauthorized();
    eaaCall('visitor', 'GET', eaaAnswers($this->open))->assertUnauthorized();
});

test('a cancelled event and an event that ended take no answer, no change and no clear: 422 before the field error', function () {
    $cancelled = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->cancelled()->create(['replay_expected' => true]);
    $over = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->past()->create();
    foreach ([$cancelled, $over] as $event) {
        EventAnswer::factory()->to($event)->of($this->c['going'])->yes()->create();
    }

    foreach ([[$cancelled, 'This event is cancelled. It takes no answers.'], [$over, 'This event is over. It takes no answers.']] as [$event, $message]) {
        // A new answer, a change, the same answer again, a wrong field and a clear
        eaaCall('member', 'PUT', eaaAnswer($event), ['answer' => 'yes'])->assertStatus(422)->assertJsonPath('message', $message);
        eaaCall('going', 'PUT', eaaAnswer($event), ['answer' => 'no'])->assertStatus(422)->assertJsonPath('message', $message);
        eaaCall('going', 'PUT', eaaAnswer($event), ['answer' => 'yes'])->assertStatus(422)->assertJsonPath('message', $message);
        eaaCall('going', 'PUT', eaaAnswer($event), ['answer' => 'maybe'])->assertStatus(422)->assertJsonPath('message', $message);
        eaaCall('going', 'DELETE', eaaAnswer($event))->assertStatus(422)->assertJsonPath('message', $message);
        // The old answer stays, and its member still reads it
        eaaCall('going', 'GET', eaaAnswer($event))->assertOk()->assertJsonPath('data.answer', 'yes');
        eaaCall('going', 'GET', eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $event))->assertOk()->assertJsonPath('data.my_answer', 'yes')->assertJsonPath('data.can_answer', false);
    }
});

test('a wrong field is a field error (400)', function (array $body) {
    eaaCall('member', 'PUT', eaaAnswer($this->event), $body)->assertStatus(400)->assertJsonStructure(['errors' => ['answer']]);
})->with([
    'no answer' => [[]],
    'an answer that does not exist' => [['answer' => 'maybe']],
    'not a text' => [['answer' => ['yes']]],
]);

test('a draft takes no answer from the people who see it', function () {
    eaaCall('host', 'PUT', eaaAnswer($this->draft), ['answer' => 'yes'])->assertStatus(422)->assertJsonPath('message', 'This event is a draft. It takes no answers.');
    eaaCall('owner', 'GET', eaaAnswers($this->draft))->assertForbidden();
    eaaCall('host', 'GET', eaaAnswer($this->draft))->assertOk()->assertJsonPath('data.answer', null);
});

// ---------------------------------------------------------------------------------------------------------------------
// The meeting address of a "going" event (rule 2, design 3.7)
// ---------------------------------------------------------------------------------------------------------------------

test('every read gives the address of a "going" event to the members who answered Yes, the host, the owner and an admin', function (string $reader) {
    foreach (eaaEventOnEveryRead($reader, $this->event) as $read => $event) {
        expect($event)->not->toBeNull($read)
            ->and($event['can_view_location'])->toBeTrue("{$reader}, {$read}")
            ->and($event['meeting_url'])->toBe(EAA_MEETING_URL, "{$reader}, {$read}")
            ->and($event['place'])->toBe(EAA_PLACE, "{$reader}, {$read}");
    }
})->with(['going', 'host', 'owner', 'admin']);

test('no read gives the address of a "going" event to the other readers', function (string $reader) {
    foreach ([eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->event), eaaEvents($this->space, '?limit=100'), eaaCommunityEvents().'?limit=100'] as $url) {
        $json = (string) eaaCall($reader, 'GET', $url)->assertOk()->getContent();

        expect(str_contains($json, 'room-of-the-yes-answers'))->toBeFalse("{$reader} {$url}")
            ->and(str_contains($json, 'old mill'))->toBeFalse("{$reader} {$url}")
            // The read does hold the event
            ->and(str_contains($json, 'Going only'))->toBeTrue("{$reader} {$url}");
    }
    foreach (eaaEventOnEveryRead($reader, $this->event) as $read => $event) {
        expect($event['can_view_location'])->toBeFalse("{$reader}, {$read}")
            ->and($event['meeting_url'])->toBeNull()
            ->and($event['place'])->toBeNull();
    }
})->with([
    'a member who answered Replay only' => ['replayer'],
    'a member who answered No' => ['refuser'],
    'a member without an answer' => ['member'],
    'a moderator without an answer' => ['moderator'],
    'a person who is not a member' => ['outsider'],
    'a visitor' => ['visitor'],
]);

test('the address goes away again when the member changes Yes to No, and when the member leaves', function () {
    expect(eaaEventOnEveryRead('going', $this->event)['one event']['meeting_url'])->toBe(EAA_MEETING_URL);

    eaaCall('going', 'PUT', eaaAnswer($this->event), ['answer' => 'no'])->assertOk();
    foreach (eaaEventOnEveryRead('going', $this->event) as $read => $event) {
        expect($event['meeting_url'])->toBeNull($read)->and($event['place'])->toBeNull($read)->and($event['can_view_location'])->toBeFalse($read);
    }

    // Yes again, then the member leaves: a person who is not a member reads the public space, never the address
    eaaCall('going', 'PUT', eaaAnswer($this->event), ['answer' => 'yes'])->assertOk();
    expect(eaaEventOnEveryRead('going', $this->event)['one event']['meeting_url'])->toBe(EAA_MEETING_URL);
    $this->c['community']->removeUser($this->c['going']);
    $left = User::findOrFail($this->c['going']->id);
    foreach (eaaEventOnEveryRead($left, $this->event) as $read => $event) {
        expect($event['meeting_url'])->toBeNull($read)->and($event['place'])->toBeNull($read)->and($event['can_view_location'])->toBeFalse($read)
            ->and($event['my_answer'])->toBeNull($read);
    }
});

test('a Yes still gives the address when the event is cancelled or over', function () {
    $this->event->update(['state' => 'cancelled']);
    expect(eaaEventOnEveryRead('going', $this->event)['one event']['meeting_url'])->toBe(EAA_MEETING_URL)
        ->and(eaaEventOnEveryRead('replayer', $this->event)['one event']['meeting_url'])->toBeNull();

    $this->event->update(['state' => 'published', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHour()]);
    expect(eaaCall('going', 'GET', eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->event))->json('data.meeting_url'))->toBe(EAA_MEETING_URL)
        ->and(eaaCall('member', 'GET', eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->event))->json('data.meeting_url'))->toBeNull();
});

// ---------------------------------------------------------------------------------------------------------------------
// What a reader never gets of the answers of the others (rules 4 and 5)
// ---------------------------------------------------------------------------------------------------------------------

test('the five fields of an event: the answer of the reader, the places that are left, and the limit only for the people who read the answers', function () {
    $fields = fn (string $reader) => array_intersect_key(
        (array) eaaCall($reader, 'GET', eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->event))->assertOk()->json('data'),
        array_flip(['my_answer', 'can_answer', 'replay_expected', 'places_left', 'capacity']),
    );
    // One Yes of 4321 places
    $left = EAA_CAPACITY - 1;

    expect($fields('going'))->toBe(['my_answer' => 'yes', 'can_answer' => true, 'replay_expected' => true, 'places_left' => $left, 'capacity' => null])
        ->and($fields('replayer'))->toBe(['my_answer' => 'replay', 'can_answer' => true, 'replay_expected' => true, 'places_left' => $left, 'capacity' => null])
        ->and($fields('refuser'))->toBe(['my_answer' => 'no', 'can_answer' => true, 'replay_expected' => true, 'places_left' => $left, 'capacity' => null])
        ->and($fields('member'))->toBe(['my_answer' => null, 'can_answer' => true, 'replay_expected' => true, 'places_left' => $left, 'capacity' => null])
        ->and($fields('moderator'))->toBe(['my_answer' => null, 'can_answer' => true, 'replay_expected' => true, 'places_left' => $left, 'capacity' => null])
        ->and($fields('host'))->toBe(['my_answer' => null, 'can_answer' => true, 'replay_expected' => true, 'places_left' => $left, 'capacity' => EAA_CAPACITY])
        ->and($fields('owner'))->toBe(['my_answer' => null, 'can_answer' => true, 'replay_expected' => true, 'places_left' => $left, 'capacity' => EAA_CAPACITY])
        ->and($fields('admin'))->toBe(['my_answer' => null, 'can_answer' => true, 'replay_expected' => true, 'places_left' => $left, 'capacity' => EAA_CAPACITY])
        // A visitor and a person who is not a member: null, false, the boolean, null, null
        ->and($fields('outsider'))->toBe(['my_answer' => null, 'can_answer' => false, 'replay_expected' => true, 'places_left' => null, 'capacity' => null])
        ->and($fields('visitor'))->toBe(['my_answer' => null, 'can_answer' => false, 'replay_expected' => true, 'places_left' => null, 'capacity' => null]);

    // Without a limit, nobody reads a number of places
    $open = eaaCall('owner', 'GET', eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->open))->assertOk();
    expect($open->json('data.places_left'))->toBeNull()->and($open->json('data.capacity'))->toBeNull()->and($open->json('data.replay_expected'))->toBeFalse();
});

test('no route gives the limit, a name or a count of the answers to a member, a moderator, a person who is not a member or a visitor', function (string $reader) {
    $reads = [
        eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->event),
        eaaEvents($this->space, '?limit=100'),
        eaaEvents($this->space, '?scope=past'),
        eaaCommunityEvents().'?limit=100',
        eaaAnswer($this->event),
        eaaAnswers($this->event),
        eaaAnswers($this->event, '?answer=replay'),
    ];
    // The limit as a JSON number or text, under any name, and any field that looks like a count
    $secrets = [':'.EAA_CAPACITY, '"'.EAA_CAPACITY.'"', '"counts"', '_count'];
    foreach (array_keys(EAA_ANSWERERS) as $key) {
        // The reader may read their own name, never the one of another member who answered
        if ($key !== $reader) {
            array_push($secrets, $this->c[$key]->name, $this->c[$key]->username, $this->c[$key]->cuid);
        }
    }

    $read = 0;
    foreach ($reads as $url) {
        $response = eaaCall($reader, 'GET', $url);
        $json = (string) $response->getContent();
        foreach ($secrets as $secret) {
            // Not `toContain`: a failure would print the whole answer
            expect(str_contains($json, $secret))->toBeFalse("{$reader} reads \"{$secret}\" in {$url}");
        }
        $read += $response->isOk() ? 1 : 0;
    }
    // The scan read real answers: the four reads of events at least, for every reader
    expect($read)->toBeGreaterThanOrEqual(4);

    if ($reader !== 'visitor') {
        $json = (string) eaaCall($reader, 'PUT', eaaAnswer($this->event), ['answer' => 'no'])->getContent();
        foreach ($secrets as $secret) {
            expect(str_contains($json, $secret))->toBeFalse("{$reader} reads \"{$secret}\" after a write");
        }
    }
})->with(['member', 'moderator', 'going', 'outsider', 'visitor']);

test('my_answer is the answer of the reader, on the event and on the lists', function () {
    EventAnswer::factory()->to($this->open)->of($this->c['going'])->no()->create();
    EventAnswer::factory()->to($this->open)->of($this->c['member'])->yes()->create();

    $answers = fn (string $reader) => array_map(
        static fn (?array $event) => $event['my_answer'] ?? null,
        [...eaaEventOnEveryRead($reader, $this->event), ...array_values(eaaEventOnEveryRead($reader, $this->open))],
    );

    expect(array_values($answers('going')))->toBe(['yes', 'yes', 'yes', 'no', 'no', 'no'])
        ->and(array_values($answers('member')))->toBe([null, null, null, 'yes', 'yes', 'yes'])
        ->and(array_values($answers('host')))->toBe([null, null, null, null, null, null])
        ->and(array_values($answers('outsider')))->toBe([null, null, null, null, null, null]);
});

// ---------------------------------------------------------------------------------------------------------------------
// The answers that the host and the team read (rule 21)
// ---------------------------------------------------------------------------------------------------------------------

test('the host, the owner and an admin read the names of Yes and of Replay only, and only the number of No', function (string $reader) {
    $yes = eaaCall($reader, 'GET', eaaAnswers($this->event))->assertOk()->assertJsonPath('message', 'Answers');
    expect(array_column((array) $yes->json('data'), 'name'))->toBe(['Gwen Goingmember'])
        ->and(array_keys((array) $yes->json('data.0')))->toBe(['cuid', 'name', 'username', 'avatar_url'])
        ->and($yes->json('metadata.counts'))->toBe(['yes' => 1, 'replay' => 1, 'no' => 1])
        ->and($yes->json('metadata.pagination.totalCount'))->toBe(1);

    $replay = eaaCall($reader, 'GET', eaaAnswers($this->event, '?answer=replay'))->assertOk();
    expect(array_column((array) $replay->json('data'), 'name'))->toBe(['Rita Replaymember']);

    // No name for "No", with any parameter, and no time of an answer
    eaaCall($reader, 'GET', eaaAnswers($this->event, '?answer=no'))->assertStatus(400)->assertJsonPath('errors.answer.0', 'Use yes or replay');
    foreach ([$yes, $replay] as $response) {
        expect((string) $response->getContent())->not->toContain('Nils Nomember')->not->toContain($this->c['refuser']->cuid)->not->toContain('_at"');
    }
})->with(['host', 'owner', 'admin']);

test('a moderator and a member do not read the answers, also with the right to delete content', function (string $reader) {
    expect($this->c['community']->fresh()->moderatorPermissions()['delete_content'])->toBeTrue();

    eaaCall($reader, 'GET', eaaAnswers($this->event))->assertForbidden();
    eaaCall($reader, 'GET', eaaAnswers($this->event, '?answer=replay'))->assertForbidden();
})->with(['moderator', 'member', 'going']);

test('the names are sorted by name, then by username, and paged', function () {
    $names = ['Zoe Last' => 'zoe', 'adam first' => 'adam-b', 'Adam First' => 'adam-a', 'Bea Middle' => 'bea'];
    foreach ($names as $name => $username) {
        $user = User::factory()->withUserProfile()->create(['name' => $name, 'username' => $username]);
        $this->c['community']->users()->attach($user, ['role' => KnowiiCommunityMemberRole::Member]);
        EventAnswer::factory()->to($this->event)->of($user)->yes()->create();
    }

    $all = eaaCall('host', 'GET', eaaAnswers($this->event))->assertOk();
    expect(array_column((array) $all->json('data'), 'username'))->toBe(['adam-a', 'adam-b', 'bea', $this->c['going']->username, 'zoe'])
        ->and($all->json('metadata.counts.yes'))->toBe(5);

    $page = eaaCall('host', 'GET', eaaAnswers($this->event, '?limit=2&offset=2'))->assertOk();
    expect(array_column((array) $page->json('data'), 'username'))->toBe(['bea', $this->c['going']->username])
        ->and($page->json('metadata.pagination.totalCount'))->toBe(5)
        ->and($page->json('metadata.pagination.nextOffset'))->toBe(4)
        ->and($page->json('metadata.pagination.previousOffset'))->toBe(0)
        // The counts are the ones of the event, not of the page
        ->and($page->json('metadata.counts'))->toBe(['yes' => 5, 'replay' => 1, 'no' => 1]);
});

test('a member who left, and a member who lost the access to the space, are in no name list and in no count', function () {
    $groups = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $buyers = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Buyers']);
    $groups->accessGroups()->attach($buyers);
    $buyers->members()->attach([$this->c['host']->id, $this->c['going']->id, $this->c['replayer']->id, $this->c['refuser']->id, $this->c['member']->id]);
    $event = EventModel::factory()->inSpace($groups)->hostedBy($this->c['host'])->create(['replay_expected' => true, 'capacity' => 10]);
    EventAnswer::factory()->to($event)->of($this->c['going'])->yes()->create();
    EventAnswer::factory()->to($event)->of($this->c['member'])->yes()->create();
    EventAnswer::factory()->to($event)->of($this->c['replayer'])->replay()->create();
    EventAnswer::factory()->to($event)->of($this->c['refuser'])->no()->create();

    $first = eaaCall('host', 'GET', eaaAnswers($event))->assertOk();
    expect(array_column((array) $first->json('data'), 'name'))->toEqualCanonicalizing(['Gwen Goingmember', $this->c['member']->name])
        ->and($first->json('metadata.counts'))->toBe(['yes' => 2, 'replay' => 1, 'no' => 1]);

    // Gwen and Nils lose the group and stay in the community: their rows stay, and no read shows them
    $buyers->members()->detach([$this->c['going']->id, $this->c['refuser']->id]);
    $after = eaaCall(User::findOrFail($this->c['host']->id), 'GET', eaaAnswers($event))->assertOk();
    expect(array_column((array) $after->json('data'), 'name'))->toBe([$this->c['member']->name])
        ->and($after->json('metadata.counts'))->toBe(['yes' => 1, 'replay' => 1, 'no' => 0])
        ->and((string) $after->getContent())->not->toContain('Gwen Goingmember')
        ->and(EventAnswer::query()->where('event_id', $event->id)->count())->toBe(4);
    // The answer of Gwen takes no place while she cannot read the space: the places follow the count that the host reads
    expect(eaaCall('host', 'GET', eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $event))->json('data.places_left'))->toBe(9);
    // Gwen gets the group again: the answer is back, with its place
    $buyers->members()->attach($this->c['going']->id);
    expect(eaaCall(User::findOrFail($this->c['host']->id), 'GET', eaaAnswers($event))->json('metadata.counts.yes'))->toBe(2)
        ->and(eaaCall('host', 'GET', eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $event))->json('data.places_left'))->toBe(8);

    // The member leaves the community: their answers go
    $this->c['community']->removeUser($this->c['member']);
    $left = eaaCall(User::findOrFail($this->c['owner']->id), 'GET', eaaAnswers($event))->assertOk();
    expect(array_column((array) $left->json('data'), 'name'))->toBe(['Gwen Goingmember'])
        ->and($left->json('metadata.counts'))->toBe(['yes' => 1, 'replay' => 1, 'no' => 0]);
});

test('the last place of a member who lost the access to the space is free: a second member takes it, and the host reads the same numbers everywhere', function () {
    $groups = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $buyers = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Buyers']);
    $groups->accessGroups()->attach($buyers);
    $buyers->members()->attach([$this->c['host']->id, $this->c['going']->id, $this->c['member']->id]);
    $event = EventModel::factory()->inSpace($groups)->hostedBy($this->c['host'])->create(['capacity' => 1]);
    $fresh = fn (string $key): User => User::findOrFail($this->c[$key]->id);
    // What the host reads: the Yes answers that the limit and the places give, the count of the list, and its names
    $hostReads = function () use ($event, $fresh): array {
        $one = eaaCall($fresh('host'), 'GET', eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $event))->assertOk();
        $inList = array_column((array) eaaCall($fresh('host'), 'GET', eaaEvents($event->space))->assertOk()->json('data'), null, 'cuid')[$event->cuid];
        $list = eaaCall($fresh('host'), 'GET', eaaAnswers($event))->assertOk();

        return [$one->json('data.capacity') - $one->json('data.places_left'), $inList['capacity'] - $inList['places_left'], $list->json('metadata.counts.yes'), array_column((array) $list->json('data'), 'name')];
    };

    eaaCall('going', 'PUT', eaaAnswer($event), ['answer' => 'yes'])->assertOk();
    eaaCall('member', 'PUT', eaaAnswer($event), ['answer' => 'yes'])->assertStatus(422)->assertJsonPath('message', 'This event is full.');
    expect($hostReads())->toBe([1, 1, 1, ['Gwen Goingmember']]);

    // Gwen loses the access group and stays in the community: her row stays, and counts for nothing
    $buyers->members()->detach($this->c['going']->id);
    expect($hostReads())->toBe([0, 0, 0, []])
        ->and(eaaCall($fresh('member'), 'GET', eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $event))->json('data.places_left'))->toBe(1);
    eaaCall($fresh('member'), 'PUT', eaaAnswer($event), ['answer' => 'yes'])->assertOk()->assertJsonPath('data.answer', 'yes');
    expect($hostReads())->toBe([1, 1, 1, [$this->c['member']->name]])
        ->and(EventAnswer::query()->where('event_id', $event->id)->where('answer', 'yes')->count())->toBe(2);
    eaaCall($fresh('going'), 'GET', eaaAnswer($event))->assertNotFound();

    // No member who can read the space has an answer: the event goes back to draft, and comes back
    eaaCall($fresh('member'), 'DELETE', eaaAnswer($event))->assertNoContent();
    $state = eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT_STATE, $event);
    eaaCall($fresh('host'), 'PUT', $state, ['state' => 'draft'])->assertOk()->assertJsonPath('data.state', 'draft');
    eaaCall($fresh('host'), 'PUT', $state, ['state' => 'published'])->assertOk();
    eaaCall($fresh('member'), 'PUT', eaaAnswer($event), ['answer' => 'yes'])->assertOk();

    // Gwen gets the group again: two Yes for one place. Nobody is removed, no place is left, and the numbers still agree
    $buyers->members()->attach($this->c['going']->id);
    $one = eaaCall($fresh('host'), 'GET', eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $event))->assertOk();
    $list = eaaCall($fresh('host'), 'GET', eaaAnswers($event))->assertOk();
    expect($one->json('data.places_left'))->toBe(0)
        ->and($one->json('data.capacity'))->toBe(1)
        ->and($list->json('metadata.counts.yes'))->toBe(2)
        // The name of the member comes from the factory: the order of the list has its own test
        ->and(array_column((array) $list->json('data'), 'name'))->toEqualCanonicalizing(['Gwen Goingmember', $this->c['member']->name])
        ->and(eaaCall($fresh('going'), 'GET', eaaAnswer($event))->json('data.answer'))->toBe('yes');
    eaaCall($fresh('host'), 'PUT', eaaAnswer($event), ['answer' => 'yes'])->assertStatus(422)->assertJsonPath('message', 'This event is full.');
});

test('one page of the answers runs the same queries with 5 answers and with 45, and gives its 2 members of 5', function () {
    $answer = function (string $name, string $username, string $answer = 'yes'): void {
        $user = User::factory()->withUserProfile()->create(['name' => $name, 'username' => $username]);
        $this->c['community']->users()->attach($user, ['role' => KnowiiCommunityMemberRole::Member]);
        EventAnswer::factory()->to($this->event)->of($user)->state(['answer' => $answer])->create();
    };
    $queries = function (string $query): array {
        // A new instance of the host: the one of the test keeps the relations that an earlier request loaded
        $host = User::findOrFail($this->c['host']->id);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = eaaCall($host, 'GET', eaaAnswers($this->event, $query))->assertOk();
        DB::disableQueryLog();

        return [count(DB::getQueryLog()), $response];
    };
    // Gwen and four more: 5 Yes
    foreach (['Zoe Last' => 'zoe', 'Bea Middle' => 'bea', 'Adam First' => 'adam', 'Yann Nearlast' => 'yann'] as $name => $username) {
        $answer($name, $username);
    }

    // A first request fills what the application keeps for the next ones
    $queries('?limit=2&offset=1');
    [$few, $page] = $queries('?limit=2&offset=1');
    expect(array_column((array) $page->json('data'), 'username'))->toBe(['bea', $this->c['going']->username])
        ->and($page->json('metadata.pagination.totalCount'))->toBe(5)
        ->and($page->json('metadata.counts'))->toBe(['yes' => 5, 'replay' => 1, 'no' => 1]);

    foreach (range(1, 40) as $i) {
        $answer("Member {$i}", "member-{$i}", ['yes', 'yes', 'replay', 'no'][$i % 4]);
    }
    [$many, $page] = $queries('?limit=2&offset=1');
    expect($many)->toBe($few)
        ->and($page->json('data'))->toHaveCount(2)
        ->and($page->json('metadata.pagination.totalCount'))->toBe(25)
        ->and($page->json('metadata.counts'))->toBe(['yes' => 25, 'replay' => 11, 'no' => 11]);
    // The answers themselves: one query for the counts and one for the members of the page
    DB::flushQueryLog();
    DB::enableQueryLog();
    EventAnswers::listOf($this->event, KnowiiEventAnswer::Yes, 2, 1);
    DB::disableQueryLog();
    expect(DB::getQueryLog())->toHaveCount(2);
});

test('a host who left reads no answer', function () {
    $this->c['community']->removeUser($this->c['host']);

    eaaCall(User::findOrFail($this->c['host']->id), 'GET', eaaAnswers($this->event))->assertForbidden();
});

// ---------------------------------------------------------------------------------------------------------------------
// Tokens, the limiter and the queries of a list
// ---------------------------------------------------------------------------------------------------------------------

test('events:read reads my answer, events:write sets it, and the answers of the others need event-answers:read', function () {
    Sanctum::actingAs($this->c['host'], ['events:read']);
    $this->getJson(eaaAnswer($this->open))->assertOk();
    $this->putJson(eaaAnswer($this->open), ['answer' => 'yes'])->assertForbidden();
    $this->deleteJson(eaaAnswer($this->open))->assertForbidden();
    $this->getJson(eaaAnswers($this->event))->assertForbidden();

    Sanctum::actingAs($this->c['host'], ['events:write']);
    $this->putJson(eaaAnswer($this->open), ['answer' => 'yes'])->assertOk();
    $this->getJson(eaaAnswer($this->open))->assertForbidden();
    $this->getJson(eaaAnswers($this->event))->assertForbidden();
    $this->deleteJson(eaaAnswer($this->open))->assertNoContent();

    Sanctum::actingAs($this->c['host'], ['event-answers:read']);
    $this->getJson(eaaAnswers($this->event))->assertOk();
    $this->getJson(eaaAnswer($this->open))->assertForbidden();

    // The ability never widens the policy: a member with the ability is refused
    Sanctum::actingAs($this->c['member'], ['event-answers:read']);
    $this->getJson(eaaAnswers($this->event))->assertForbidden();
});

test('with a token, the limit of an event needs event-answers:read; the places follow the rule for members', function () {
    $fields = function (array $abilities): array {
        Sanctum::actingAs(User::findOrFail($this->c['host']->id), $abilities);
        $one = $this->getJson(eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->event))->assertOk();
        $ofSpace = array_column((array) $this->getJson(eaaEvents($this->space, '?limit=100'))->assertOk()->json('data'), null, 'cuid')[$this->event->cuid];
        $ofCommunity = array_column((array) $this->getJson(eaaCommunityEvents().'?limit=100')->assertOk()->json('data'), null, 'cuid')[$this->event->cuid];
        // No read of the token holds the limit under any name
        $holdsLimit = str_contains((string) $one->getContent().json_encode([$ofSpace, $ofCommunity]), (string) EAA_CAPACITY);

        return [[$one->json('data.places_left'), $ofSpace['places_left'], $ofCommunity['places_left']], [$one->json('data.capacity'), $ofSpace['capacity'], $ofCommunity['capacity']], $holdsLimit];
    };
    $left = EAA_CAPACITY - 1;

    // The host with a token that only reads events: what a member reads
    expect($fields(['events:read']))->toBe([[$left, $left, $left], [null, null, null], false])
        // With the ability that reads the answers: the limit too
        ->and($fields(['events:read', 'event-answers:read']))->toBe([[$left, $left, $left], [EAA_CAPACITY, EAA_CAPACITY, EAA_CAPACITY], true]);

    // A write with a token that does not read the answers gives no limit back; the limit is stored
    Sanctum::actingAs(User::findOrFail($this->c['host']->id), ['events:write']);
    $updated = $this->patchJson(eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->event), ['capacity' => 77])->assertOk();
    expect($updated->json('data.capacity'))->toBeNull()
        ->and($updated->json('data.places_left'))->toBe(76)
        ->and($this->event->fresh()->capacity)->toBe(77);
    Sanctum::actingAs(User::findOrFail($this->c['host']->id), ['events:write', 'event-answers:read']);
    expect($this->patchJson(eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->event), ['capacity' => 78])->assertOk()->json('data.capacity'))->toBe(78);

    // The ability never widens the policy: a member with it reads no limit
    Sanctum::actingAs(User::findOrFail($this->c['member']->id), ['events:read', 'event-answers:read']);
    $member = $this->getJson(eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->event))->assertOk();
    expect($member->json('data.capacity'))->toBeNull()->and($member->json('data.places_left'))->toBe(77);

    // A session does not change: the host reads the limit
    app('auth')->forgetGuards();
    expect(eaaCall(User::findOrFail($this->c['host']->id), 'GET', eaaPath(ApiRoutes::COMMUNITY_SPACE_EVENT, $this->event))->json('data.capacity'))->toBe(78);
});

test('event-answers:read exists and is not in the default set of a new token', function () {
    expect(Jetstream::$permissions)->toContain('event-answers:read')
        ->and(Jetstream::$defaultPermissions)->not->toContain('event-answers:read')->not->toContain('events:write')->toContain('events:read');
});

test('answers and clears are limited per member', function () {
    $this->actingAs($this->c['member']);
    foreach (range(1, Constants::EVENT_ANSWERS_PER_MINUTE) as $i) {
        $response = $i % 2 === 0 ? $this->deleteJson(eaaAnswer($this->open)) : $this->putJson(eaaAnswer($this->open), ['answer' => 'yes']);
        expect($response->getStatusCode())->toBeLessThan(300);
    }

    $this->putJson(eaaAnswer($this->open), ['answer' => 'no'])->assertStatus(429);
    $this->deleteJson(eaaAnswer($this->open))->assertStatus(429);
    // A read is not limited by it, and another member is not held
    $this->getJson(eaaAnswer($this->open))->assertOk();
    eaaCall('going', 'PUT', eaaAnswer($this->open), ['answer' => 'yes'])->assertOk();
});

test('a list of 20 events runs as many queries as a list of 2: the answers and the places load once for the page', function (string $reader) {
    $count = function (string $url) use ($reader): int {
        // A new instance of the reader: the one of the test keeps the relations that an earlier request loaded
        $user = User::findOrFail($this->c[$reader]->id);
        DB::flushQueryLog();
        DB::enableQueryLog();
        eaaCall($user, 'GET', $url)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    // The draft is in the list of its host, of the owner and of the admins: without it, every reader gets the same events
    $this->draft->delete();
    $urls = [eaaEvents($this->space, '?limit=100'), eaaCommunityEvents().'?limit=100'];
    $few = array_map($count, $urls);

    foreach (range(1, 18) as $i) {
        $event = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create([
            'location_visibility' => $i % 2 === 0 ? KnowiiEventLocationVisibility::Going : KnowiiEventLocationVisibility::Members,
            'capacity' => $i % 3 === 0 ? null : 10 + $i,
        ]);
        EventAnswer::factory()->to($event)->of($this->c['going'])->yes()->create();
        EventAnswer::factory()->to($event)->of($this->c['member'])->state(['answer' => $i % 2 === 0 ? 'yes' : 'no'])->create();
    }

    $list = eaaCall($reader, 'GET', $urls[0])->assertOk();
    expect($list->json('metadata.pagination.totalCount'))->toBe(20)
        ->and(array_map($count, $urls))->toBe($few);
})->with(['member', 'going', 'moderator', 'host', 'owner']);
