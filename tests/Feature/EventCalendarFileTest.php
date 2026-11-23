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
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Jetstream\Jetstream;
use Laravel\Sanctum\Sanctum;

/*
 * The calendar file of an event (#1515, ADR 0053, "The calendar file and My events"): who gets it, in which order the
 * others are refused, what it holds for each reader, and what the route never stores. The values of the people, the
 * community, the spaces and the events are fixed: a scan of a file looks for them.
 */

const ECF_MEETING_URL = 'https://meet.example.com/room-of-the-calendar-file';

const ECF_PLACE = 'Attic of the old mill';

const ECF_PASSWORD = 'the-password-of-the-calendar-file-2026';

/**
 * The people of the tests, with fixed names, usernames and addresses
 *
 * @var array<string, string>
 */
const ECF_PEOPLE = [
    'owner' => 'Olga Owner',
    'admin' => 'Adam Admin',
    'moderator' => 'Mona Moderator',
    'host' => 'Hester Host',
    'member' => 'Mel Member',
    'going' => 'Gwen Goingmember',
    'replayer' => 'Rita Replaymember',
    'refuser' => 'Nils Nomember',
    'outsider' => 'Otto Outsider',
];

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10T09:00:00Z'));
    Event::fake([SpaceTreeChanged::class, SpaceContentChanged::class]);
    RateLimiter::clear('calendar-files');
    RateLimiter::clear('event-answers');

    $people = [];
    foreach (ECF_PEOPLE as $key => $name) {
        $handle = strtolower(str_replace(' ', '-', $name));
        $people[$key] = User::factory()->withUserProfile()->create(['name' => $name, 'username' => $handle, 'email' => $handle.'@example.com']);
    }
    $community = Community::factory()->create(['owner_id' => $people['owner']->id, 'name' => 'Example community', 'description' => 'A community of the tests']);
    foreach (['admin' => KnowiiCommunityMemberRole::Admin, 'moderator' => KnowiiCommunityMemberRole::Moderator, 'host' => KnowiiCommunityMemberRole::Member, 'member' => KnowiiCommunityMemberRole::Member, 'going' => KnowiiCommunityMemberRole::Member, 'replayer' => KnowiiCommunityMemberRole::Member, 'refuser' => KnowiiCommunityMemberRole::Member] as $key => $role) {
        $community->users()->attach($people[$key], ['role' => $role]);
    }
    $this->c = ['community' => $community, ...$people];

    $this->group = SpaceGroup::factory()->create(['community_id' => $community->id, 'name' => 'Example group']);
    $make = fn (string $name, KnowiiSpaceVisibility $visibility) => Space::factory()->inGroup($this->group)->create([
        'name' => $name, 'type' => KnowiiSpaceType::Events, 'visibility' => $visibility, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators,
    ]);
    $this->space = $make('Office hours', KnowiiSpaceVisibility::Public);
    $this->membersSpace = $make('Member hours', KnowiiSpaceVisibility::Members);
    $this->groupsSpace = $make('Buyer hours', KnowiiSpaceVisibility::Groups);
    $this->buyers = AccessGroup::query()->create(['community_id' => $community->id, 'name' => 'Buyers']);
    $this->groupsSpace->accessGroups()->attach($this->buyers);
    $this->buyers->members()->attach([$people['member']->id]);

    $event = fn (Space $space, string $title, array $more = []) => EventModel::factory()->inSpace($space)->hostedBy($people['host'])->create([
        'title' => $title,
        'body' => 'Bring your questions about the weekly review.',
        'starts_at' => CarbonImmutable::parse('2026-11-18T16:00:00Z'),
        'ends_at' => CarbonImmutable::parse('2026-11-18T17:00:00Z'),
        'time_zone' => 'Europe/Brussels',
        'meeting_url' => ECF_MEETING_URL,
        'place' => ECF_PLACE,
        ...$more,
    ]);
    // A "going" event of a public space: Gwen said Yes, Rita Replay only, Nils No, Mel did not answer
    $this->event = $event($this->space, 'Weekly review office hour', ['location_visibility' => KnowiiEventLocationVisibility::Going, 'replay_expected' => true, 'sequence' => 2]);
    EventAnswer::factory()->to($this->event)->of($people['going'])->yes()->create();
    EventAnswer::factory()->to($this->event)->of($people['replayer'])->replay()->create();
    EventAnswer::factory()->to($this->event)->of($people['refuser'])->no()->create();
    // The address for every member
    $this->open = $event($this->membersSpace, 'Open office hour');
    $this->inGroups = $event($this->groupsSpace, 'Buyer office hour');
    $this->draft = $event($this->space, 'A draft of the host', ['state' => 'draft']);
    // The rows were written at the frozen time: a later write is later
    $this->travel(1)->hours();
});

function ecfPath(EventModel $event, ?Space $space = null, ?string $eventCuid = null): string
{
    $space ??= $event->space;

    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENT_CALENDAR_FILE, [
        ApiRoutes::PARAM_COMMUNITY => test()->c['community']->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        ApiRoutes::PARAM_EVENT => $eventCuid ?? $event->cuid,
    ]);
}

/**
 * The request of a browser that follows the link of the page: it asks for a page, not for JSON
 */
function ecfGet(string|User $reader, EventModel $event): TestResponse
{
    $test = test();
    if ($reader === 'visitor') {
        app('auth')->forgetGuards();

        return $test->get(ecfPath($event), ['Accept' => 'text/html']);
    }

    return $test->actingAs($reader instanceof User ? $reader : User::query()->findOrFail($test->c[$reader]->id))->get(ecfPath($event), ['Accept' => 'text/html']);
}

/**
 * A browser that signed in on the sign-in page: the cookie of its session. The memory of the test then holds no signed-in
 * account, so that each request reads the session of its cookie, as a real browser makes it do
 *
 * @return array<string, string>
 */
function ecfSignIn(string $reader): array
{
    $user = User::query()->findOrFail(test()->c[$reader]->id);
    $user->forceFill(['password' => Hash::make(ECF_PASSWORD)])->save();
    ecfNewBrowser();

    $response = test()->post('/login', ['email' => $user->email, 'password' => ECF_PASSWORD])->assertRedirect();
    $name = config()->string('session.cookie');
    $value = $response->getCookie($name)?->getValue();
    expect($value)->toBeString()->not->toBe('');

    return [$name => (string) $value];
}

/**
 * A new request of a browser that holds no cookie and no header yet: the sessions that the store holds stay
 */
function ecfNewBrowser(): void
{
    session()->flush();
    app('auth')->shouldUse('web');
    app('auth')->forgetGuards();
    test()->flushHeaders();
    $case = test()->target;
    Closure::bind(function (): void {
        $this->defaultCookies = [];
    }, $case, $case::class)();
}

/**
 * The request of a browser with the cookie of its session. A click on the link of the page (also "open in a new tab")
 * sends the address of the page as the Referer; a typed address, a pasted address and a bookmark send none
 *
 * @param  array<string, string>  $cookies
 */
function ecfBrowserGet(array $cookies, EventModel $event, bool $fromThePage): TestResponse
{
    ecfNewBrowser();
    $headers = ['Accept' => 'text/html'];
    if ($fromThePage) {
        $headers['Referer'] = route('events.show', ['communitySlug' => 'example-community', 'spaceSlug' => $event->space->slug, 'eventSlug' => $event->slug]);
    }

    return test()->withCookies($cookies)->get(ecfPath($event), $headers);
}

/**
 * The file of a reader, unfolded (RFC 5545 3.1): a long value is on one line again, so that a search finds it whole
 */
function ecfFile(string|User $reader, EventModel $event): string
{
    $response = ecfGet($reader, $event)->assertOk();

    return str_replace("\r\n ", '', (string) $response->getContent());
}

/**
 * The value of one property of a file, after the unfold
 */
function ecfValue(string $file, string $name): ?string
{
    foreach (explode("\r\n", str_replace("\r\n ", '', $file)) as $line) {
        if (str_starts_with($line, $name.':')) {
            return substr($line, strlen($name) + 1);
        }
    }

    return null;
}

/**
 * The event as `GET .../events/{event}` gives it to the reader
 *
 * @return array<string, mixed>
 */
function ecfEventRead(string $reader, EventModel $event): array
{
    $url = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENT, [ApiRoutes::PARAM_COMMUNITY => test()->c['community']->cuid, ApiRoutes::PARAM_SPACE => $event->space->cuid, ApiRoutes::PARAM_EVENT => $event->cuid]);

    return (array) test()->actingAs(User::query()->findOrFail(test()->c[$reader]->id))->getJson($url)->assertOk()->json('data');
}

// ---------------------------------------------------------------------------------------------------------------------
// The answer
// ---------------------------------------------------------------------------------------------------------------------

test('the file is an attachment that the browser cannot run and that no shared cache keeps, named after the slug and the date in the zone of the event', function () {
    // 23:30 UTC is already the next day in Singapore
    $late = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create([
        'title' => 'Late review', 'starts_at' => CarbonImmutable::parse('2026-11-18T23:30:00Z'), 'ends_at' => CarbonImmutable::parse('2026-11-19T00:30:00Z'), 'time_zone' => 'Asia/Singapore',
    ]);

    $response = ecfGet('member', $late)->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/calendar; charset=utf-8')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="late-review-2026-11-19.ics"')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
        ->and(ecfValue((string) $response->getContent(), 'DTSTART'))->toBe('20261118T233000Z')
        // Every reader gets the same name
        ->and(ecfGet('owner', $late)->headers->get('Content-Disposition'))->toBe('attachment; filename="late-review-2026-11-19.ics"');
});

test('the file holds the lines of the design, the address of the page and the sequence of the event', function () {
    $file = ecfFile('going', $this->event);
    $page = route('events.show', ['communitySlug' => 'example-community', 'spaceSlug' => 'office-hours', 'eventSlug' => 'weekly-review-office-hour']);

    expect(ecfValue($file, 'UID'))->toBe($this->event->cuid)
        ->and(ecfValue($file, 'DTSTART'))->toBe('20261118T160000Z')
        ->and(ecfValue($file, 'DTEND'))->toBe('20261118T170000Z')
        ->and(ecfValue($file, 'SUMMARY'))->toBe('Weekly review office hour')
        ->and(ecfValue($file, 'URL'))->toBe($page)
        ->and(ecfValue($file, 'SEQUENCE'))->toBe('2')
        ->and(ecfValue($file, 'STATUS'))->toBe('CONFIRMED')
        ->and(ecfValue($file, 'DESCRIPTION'))->toBe('Bring your questions about the weekly review.\\n\\nEvent page: '.$page.'\\nMeeting: '.ECF_MEETING_URL)
        ->and(ecfValue($file, 'LOCATION'))->toBe(ECF_PLACE)
        ->and($file)->not->toContain('METHOD')->not->toContain('VALARM')->not->toContain('ORGANIZER')->not->toContain('ATTENDEE');
});

// ---------------------------------------------------------------------------------------------------------------------
// The refusals, in their order (rule 2)
// ---------------------------------------------------------------------------------------------------------------------

test('a visitor gets 401 and no file, also in a public space, and the refusal is the JSON error of the API', function () {
    $response = ecfGet('visitor', $this->event)->assertUnauthorized();

    expect($response->headers->get('Content-Type'))->toStartWith('application/json')
        ->and($response->headers->get('Content-Disposition'))->toBeNull()
        ->and($response->json('message'))->toBeString();
});

test('a browser gets the file only through the link of the page: the same session without the address of a page as the Referer gets 401, as a typed address, a pasted address or a bookmark', function () {
    $member = ecfSignIn('member');
    ecfBrowserGet($member, $this->open, fromThePage: true)->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    $typed = ecfBrowserGet($member, $this->open, fromThePage: false)->assertUnauthorized();
    expect($typed->headers->get('Content-Type'))->toStartWith('application/json')
        ->and($typed->headers->get('Content-Disposition'))->toBeNull();

    // A person who is not a member: 403 through the address of a page, 401 for a typed address
    $outsider = ecfSignIn('outsider');
    $refused = ecfBrowserGet($outsider, $this->event, fromThePage: true)->assertForbidden();
    expect($refused->json('message'))->toBe('Only the members of the community get the calendar file of an event');
    ecfBrowserGet($outsider, $this->event, fromThePage: false)->assertUnauthorized();
});

test('a session that a takeover ended gets 401 as the JSON error of the API, never the sign-in page', function () {
    $member = ecfSignIn('member');
    ecfBrowserGet($member, $this->open, fromThePage: true)->assertOk();

    // A takeover adds 1 to the version of the sessions of the account (ADR 0059)
    User::query()->whereKey($this->c['member']->id)->update(['session_version' => 1]);

    $ended = ecfBrowserGet($member, $this->open, fromThePage: true)->assertUnauthorized();
    expect($ended->headers->get('Content-Type'))->toStartWith('application/json')
        ->and($ended->headers->get('Location'))->toBeNull()
        ->and($ended->headers->get('Content-Disposition'))->toBeNull()
        ->and($ended->json('message'))->toBeString();
    $this->assertGuest();
    // The session is gone: the next request of the same browser is a visitor
    ecfBrowserGet($member, $this->open, fromThePage: true)->assertUnauthorized();
});

test('a member who cannot read the space gets the body of an event that does not exist', function () {
    $missing = ecfGet('going', $this->inGroups)->assertNotFound();
    $this->app['auth']->forgetGuards();
    $none = $this->actingAs($this->c['going'])->get(ecfPath($this->inGroups, eventCuid: 'nothing-here'), ['Accept' => 'text/html'])->assertNotFound();

    expect($missing->headers->get('Content-Type'))->toStartWith('application/json')
        ->and($missing->json())->toBe($none->json());
});

test('in production, a plain link to the file of an event that does not exist gets the JSON 404 of the API (#1716)', function () {
    configuredInstance();
    app()->detectEnvironment(fn () => 'production');

    $response = $this->actingAs($this->c['member'])->get(ecfPath($this->event, eventCuid: 'nothing-here'))->assertNotFound();

    $response->assertHeader('Content-Type', 'application/json')->assertJsonPath('type', 'notFound');
    expect((string) $response->getContent())->not->toContain('"component"');
});

test('a person who is not a member gets 403 in a public space, and 404 in another space', function () {
    $response = ecfGet('outsider', $this->event)->assertForbidden();

    expect($response->json('message'))->toBe('Only the members of the community get the calendar file of an event')
        ->and($response->headers->get('Content-Disposition'))->toBeNull();
    ecfGet('outsider', $this->open)->assertNotFound();
});

test('a draft gives no file: 422 for its host, the owner and the admins, and the body of an event that does not exist for the others', function () {
    foreach (['host', 'owner', 'admin'] as $reader) {
        $response = ecfGet($reader, $this->draft)->assertStatus(422);
        expect($response->json('message'))->toBe('This event is a draft. It has no calendar file.')
            ->and($response->headers->get('Content-Disposition'))->toBeNull();
    }
    foreach (['member', 'moderator', 'going'] as $reader) {
        ecfGet($reader, $this->draft)->assertNotFound();
    }
});

test('a cancelled event gives a file that says so, and an event that ended gives a file too', function () {
    $this->event->update(['state' => 'cancelled']);
    $file = ecfFile('going', $this->event);

    expect(ecfValue($file, 'STATUS'))->toBe('CANCELLED')
        ->and(ecfValue($file, 'SUMMARY'))->toBe('Cancelled: Weekly review office hour');

    $this->travelTo(CarbonImmutable::parse('2026-11-25T09:00:00Z'));
    expect(ecfValue(ecfFile('member', $this->open), 'STATUS'))->toBe('CONFIRMED');
});

// ---------------------------------------------------------------------------------------------------------------------
// The meeting address and the place: only for a reader who may see them (rules 13, 70, 71)
// ---------------------------------------------------------------------------------------------------------------------

test('the file holds the meeting address and the place exactly when the read of the event gives them to the same reader', function (string $reader, bool $sees) {
    $file = ecfFile($reader, $this->event);
    $read = ecfEventRead($reader, $this->event);

    expect(str_contains($file, ECF_MEETING_URL))->toBe($sees)
        ->and(str_contains($file, ECF_PLACE))->toBe($sees)
        ->and($read['meeting_url'] !== null)->toBe($sees)
        ->and($read['place'] !== null)->toBe($sees)
        ->and(ecfValue($file, 'LOCATION'))->toBe($sees ? ECF_PLACE : null);
})->with([
    'the owner' => ['owner', true],
    'an admin' => ['admin', true],
    'the host' => ['host', true],
    'a member who answered Yes' => ['going', true],
    'a member who answered Replay only' => ['replayer', false],
    'a member who answered No' => ['refuser', false],
    'a member who did not answer' => ['member', false],
    'a moderator without a Yes' => ['moderator', false],
]);

test('every member gets the meeting address and the place of an event for the members', function (string $reader) {
    $file = ecfFile($reader, $this->open);

    expect($file)->toContain(ECF_MEETING_URL)->toContain(ECF_PLACE)
        ->and(ecfEventRead($reader, $this->open)['meeting_url'])->toBe(ECF_MEETING_URL);
})->with(['owner', 'admin', 'moderator', 'host', 'member', 'going', 'replayer', 'refuser']);

test('after a change from Yes to No, the next file holds neither the meeting address nor the place', function () {
    expect(ecfFile('going', $this->event))->toContain(ECF_MEETING_URL);

    $this->actingAs($this->c['going'])->putJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENT_ANSWER, [
        ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->space->cuid, ApiRoutes::PARAM_EVENT => $this->event->cuid,
    ]), ['answer' => 'no'])->assertOk();

    $file = ecfFile('going', $this->event);
    expect($file)->not->toContain(ECF_MEETING_URL)->not->toContain(ECF_PLACE)
        ->and(ecfValue($file, 'LOCATION'))->toBeNull();
});

test('two readers with the same rights whose answers have the same time get the same bytes, and a reader who sees the address gets a file that differs only in the description, the place and the stamp', function () {
    // The fixture wrote every answer at the same frozen time
    expect(ecfFile('replayer', $this->event))->toBe(ecfFile('refuser', $this->event))
        ->and(ecfFile('owner', $this->event))->toBe(ecfFile('admin', $this->event));

    $names = fn (string $file) => array_map(fn (string $line) => explode(':', $line, 2)[0], explode("\r\n", str_replace("\r\n ", '', $file)));
    $sees = explode("\r\n", str_replace("\r\n ", '', ecfFile('going', $this->event)));
    $without = explode("\r\n", str_replace("\r\n ", '', ecfFile('member', $this->event)));
    $differ = array_values(array_map(fn (string $line) => explode(':', $line, 2)[0], array_diff($sees, $without)));

    // DTSTAMP too when the answer of the reader is later than the last change of the event (rule 10): here it is not
    expect($differ)->toBe(['DESCRIPTION', 'LOCATION'])
        ->and(array_values(array_diff($names(implode("\r\n", $without)), $names(implode("\r\n", $sees)))))->toBe([]);
});

// ---------------------------------------------------------------------------------------------------------------------
// The stamp and the sequence (rules 10 and 6 of the design)
// ---------------------------------------------------------------------------------------------------------------------

test('two readers with the same rights whose answers have different times get files that differ only in the stamp: it follows the answer of each reader', function () {
    // Rita answered after the last change of the event, Nils did not
    EventAnswer::query()->where('event_id', $this->event->id)->where('user_id', $this->c['replayer']->id)
        ->update(['updated_at' => CarbonImmutable::parse('2026-11-10T09:30:00Z')]);

    $replayer = explode("\r\n", ecfFile('replayer', $this->event));
    $refuser = explode("\r\n", ecfFile('refuser', $this->event));

    expect(array_values(array_diff($replayer, $refuser)))->toBe(['DTSTAMP:20261110T093000Z'])
        ->and(array_values(array_diff($refuser, $replayer)))->toBe(['DTSTAMP:20261110T090000Z']);
});

test('two downloads of the same data give the same bytes: the stamp is the last revision, never the time of the request', function () {
    $first = ecfFile('member', $this->open);
    $this->travel(10)->minutes();

    expect(ecfFile('member', $this->open))->toBe($first)
        ->and(ecfValue($first, 'DTSTAMP'))->toBe('20261110T090000Z');
});

test('after a Yes on a "going" event, the stamp is the time of the answer and the address is in the file', function () {
    expect(ecfFile('member', $this->event))->not->toContain(ECF_MEETING_URL);

    $this->travelTo(CarbonImmutable::parse('2026-11-12T14:15:00Z'));
    $this->actingAs($this->c['member'])->putJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENT_ANSWER, [
        ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->space->cuid, ApiRoutes::PARAM_EVENT => $this->event->cuid,
    ]), ['answer' => 'yes'])->assertOk();
    $this->travel(5)->minutes();

    $file = ecfFile('member', $this->event);
    expect(ecfValue($file, 'DTSTAMP'))->toBe('20261112T141500Z')
        ->and(ecfValue($file, 'LOCATION'))->toBe(ECF_PLACE)
        ->and($file)->toContain(ECF_MEETING_URL);
});

test('a moved event gives the same UID, a higher sequence and a later stamp', function () {
    $before = ecfFile('going', $this->event);

    $this->travelTo(CarbonImmutable::parse('2026-11-16T08:00:00Z'));
    $this->actingAs($this->c['host'])->patchJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENT, [
        ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->space->cuid, ApiRoutes::PARAM_EVENT => $this->event->cuid,
    ]), ['starts_at' => '2026-11-18T18:00:00+01:00', 'ends_at' => '2026-11-18T19:00:00+01:00'])->assertOk();
    $after = ecfFile('going', $this->event);

    expect(ecfValue($after, 'UID'))->toBe(ecfValue($before, 'UID'))
        ->and((int) ecfValue($after, 'SEQUENCE'))->toBeGreaterThan((int) ecfValue($before, 'SEQUENCE'))
        ->and(ecfValue($after, 'DTSTAMP'))->toBe('20261116T080000Z')
        ->and(ecfValue($before, 'DTSTAMP'))->toBe('20261110T090000Z')
        ->and(ecfValue($after, 'DTSTART'))->toBe('20261118T170000Z');
});

// ---------------------------------------------------------------------------------------------------------------------
// What the file never holds, and what the route never stores (rules 14, 24 and 72)
// ---------------------------------------------------------------------------------------------------------------------

test('no file names a person: no name, no username, no email address and no cuid of any member, for any reader', function () {
    $people = array_map(fn (string $key) => User::query()->findOrFail($this->c[$key]->id), array_keys(ECF_PEOPLE));
    foreach (array_keys(ECF_PEOPLE) as $reader) {
        if ($reader === 'outsider') {
            continue;
        }
        $file = str_replace("\r\n ", '', ecfFile($reader, $this->event));
        foreach ($people as $person) {
            expect(stripos($file, $person->name))->toBeFalse()
                ->and(stripos($file, $person->username))->toBeFalse()
                ->and(stripos($file, $person->email))->toBeFalse()
                ->and(str_contains($file, $person->cuid))->toBeFalse();
        }
        // No name of the community or of the space as a line, no answer, no count, no limit
        expect($file)->not->toContain('Example community')->not->toContain('Office hours')
            ->not->toContain('replay')->not->toContain('Replay');
    }
});

test('a download writes no row, no file and no log line of its own', function () {
    Storage::fake();
    Log::spy();
    $member = User::query()->findOrFail($this->c['going']->id);
    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql) === 1) {
            $writes[] = $query->sql;
        }
    });

    $this->actingAs($member)->get(ecfPath($this->event), ['Accept' => 'text/html'])->assertOk();

    expect($writes)->toBe([])
        ->and(Storage::allFiles())->toBe([]);
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('notice');
    Log::shouldNotHaveReceived('warning');
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('debug');
    Log::shouldNotHaveReceived('log');
});

// ---------------------------------------------------------------------------------------------------------------------
// Tokens and the limiter
// ---------------------------------------------------------------------------------------------------------------------

test('a token without events:read gets 403, and a token of the default set gets the file', function () {
    Sanctum::actingAs(User::query()->findOrFail($this->c['member']->id), ['posts:read']);
    $this->get(ecfPath($this->open))->assertForbidden();

    Sanctum::actingAs(User::query()->findOrFail($this->c['member']->id), Jetstream::$defaultPermissions);
    expect(Jetstream::$defaultPermissions)->toContain('events:read');
    $this->get(ecfPath($this->open))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
});

test('the limiter gives 30 files a minute to a member, then 429 as the JSON error of the API', function () {
    $member = User::query()->findOrFail($this->c['member']->id);
    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($member)->get(ecfPath($this->open), ['Accept' => 'text/html'])->assertOk();
    }

    $refused = $this->actingAs($member)->get(ecfPath($this->open), ['Accept' => 'text/html'])->assertStatus(429);
    expect($refused->headers->get('Content-Type'))->toStartWith('application/json')
        ->and($refused->headers->get('Content-Disposition'))->toBeNull();
});

// ---------------------------------------------------------------------------------------------------------------------
// Leave, groups, a deleted account (rules 73 to 76)
// ---------------------------------------------------------------------------------------------------------------------

test('a member who left the community gets 403 in a public space and 404 in another space', function () {
    ecfGet('member', $this->event)->assertOk();
    $this->c['community']->removeUser(User::query()->findOrFail($this->c['member']->id));

    ecfGet('member', $this->event)->assertForbidden();
    ecfGet('member', $this->open)->assertNotFound();
});

test('a member who lost the access group of a "groups" space gets the body of an event that does not exist, and the file again when the access is back', function () {
    EventAnswer::factory()->to($this->inGroups)->of($this->c['member'])->yes()->create();
    expect(ecfFile('member', $this->inGroups))->toContain(ECF_MEETING_URL);

    $this->buyers->members()->detach($this->c['member']->id);
    $gone = ecfGet('member', $this->inGroups)->assertNotFound();
    $this->app['auth']->forgetGuards();
    $none = $this->actingAs(User::query()->findOrFail($this->c['member']->id))->get(ecfPath($this->inGroups, eventCuid: 'nothing-here'), ['Accept' => 'text/html'])->assertNotFound();
    expect($gone->json())->toBe($none->json())
        // The answer stays
        ->and(EventAnswer::query()->where('event_id', $this->inGroups->id)->where('user_id', $this->c['member']->id)->exists())->toBeTrue();

    $this->buyers->members()->attach($this->c['member']->id);
    expect(ecfFile('member', $this->inGroups))->toContain(ECF_MEETING_URL);
});

test('the event of a deleted host stays without a host, and its file does not change: it never named the host', function () {
    $before = ecfFile('going', $this->event);

    app(DeletesAccounts::class)->delete(User::query()->findOrFail($this->c['host']->id));

    expect($this->event->fresh()->host_id)->toBeNull()
        ->and(ecfFile('going', $this->event))->toBe($before);
});

test('the owner and an admin never get the file of a draft of another host, and a member gets the body of an event that does not exist', function () {
    $other = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['going'])->draft()->create(['title' => 'A draft of Gwen']);

    ecfGet('owner', $other)->assertStatus(422);
    ecfGet('admin', $other)->assertStatus(422);
    ecfGet('member', $other)->assertNotFound();
    ecfGet('going', $other)->assertStatus(422);
});
