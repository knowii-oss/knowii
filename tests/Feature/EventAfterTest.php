<?php

use App\ApiRoutes;
use App\Contracts\Content\RendersContentAgain;
use App\Contracts\Events\DuplicatesEvents;
use App\Contracts\Events\SetsEventStates;
use App\Contracts\Events\UpdatesEvents;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiEventLocationVisibility;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceContentChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Event as EventModel;
use App\Models\EventAnswer;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;

/*
 * After the event, slice E3 (#1516, ADR 0053, "Comments and after the event"): the replay link and the notes of the host
 * (rules 22 to 26), the copy (rule 6), and who reads the replay on every route that carries an event (rules 27 to 30 and
 * 50). The server never opens the replay link: the addresses of the tests are never fetched.
 */

// The two video hosts of lessons, as CourseSpacesTest writes them, and an address of another place
const EAF_YOUTUBE = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

const EAF_VIMEO = 'https://vimeo.com/76979871';

const EAF_OTHER = 'https://replays.example.com/recording-of-the-office-hour';

// The readers of rule 50, with the answer of each member to the events
const EAF_READERS = ['visitor', 'outsider', 'member', 'yes', 'replay', 'no', 'moderator', 'host', 'admin', 'owner'];

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10T09:00:00Z'));
    Event::fake([SpaceTreeChanged::class, SpaceContentChanged::class]);
    $this->c = communityWithMembers();
    foreach (['host', 'yes', 'replay', 'no'] as $key) {
        $this->c[$key] = User::factory()->withUserProfile()->create();
        $this->c['community']->users()->attach($this->c[$key], ['role' => KnowiiCommunityMemberRole::Member]);
    }
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->space = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Public, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    $this->event = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->past()->create(['title' => 'Weekly review office hour', 'replay_expected' => true]);
});

function eafPath(EventModel $event): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENT, [
        ApiRoutes::PARAM_COMMUNITY => $event->community->cuid,
        ApiRoutes::PARAM_SPACE => $event->space->cuid,
        ApiRoutes::PARAM_EVENT => $event->cuid,
    ]);
}

function eafAs(?string $reader): void
{
    if ($reader === null || $reader === 'visitor') {
        app('auth')->forgetGuards();

        return;
    }
    test()->actingAs(User::query()->findOrFail(test()->c[$reader]->id));
}

/**
 * @param  array<string, mixed>  $input
 */
function eafPatch(string $reader, EventModel $event, array $input): TestResponse
{
    eafAs($reader);

    return test()->patchJson(eafPath($event), $input);
}

// The replay link and the notes of the host (rules 22 to 26)
// ---------------------------------------------------------------------------------------------------------------------

test('the host adds a replay link and notes to a published event that ended', function (string $field, string $value) {
    $response = eafPatch('host', $this->event, [$field => $value])->assertOk();

    expect($this->event->fresh()->{$field})->toBe($value)
        ->and($response->json('data.'.$field))->toBe($value);
})->with([
    'the replay link' => ['replay_url', EAF_OTHER],
    'the notes' => ['notes', 'The **slides** of the session'],
]);

test('a replay link or notes are refused before the end, on a cancelled event and on a draft, with the words of the field', function (string $state, string $field, string $message) {
    $event = match ($state) {
        'future' => EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create(),
        'now' => EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create(['starts_at' => now()->subMinutes(30), 'ends_at' => now()->addMinutes(30)]),
        'cancelled' => EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->past()->cancelled()->create(),
        'draft' => EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->past()->draft()->create(),
    };

    eafPatch('host', $event, [$field => $field === 'replay_url' ? EAF_OTHER : 'Some notes'])
        ->assertStatus(400)
        ->assertJsonPath('errors.'.$field.'.0', $message);

    expect($event->fresh()->replay_url)->toBeNull()->and($event->fresh()->notes)->toBe('');
})->with([
    'future, the link' => ['future', 'replay_url', 'Add the replay once the event is published and over.'],
    'future, the notes' => ['future', 'notes', 'Add the notes once the event is published and over.'],
    'happening now' => ['now', 'replay_url', 'Add the replay once the event is published and over.'],
    'cancelled, the link' => ['cancelled', 'replay_url', 'Add the replay once the event is published and over.'],
    'cancelled, the notes' => ['cancelled', 'notes', 'Add the notes once the event is published and over.'],
    'draft, the link' => ['draft', 'replay_url', 'Add the replay once the event is published and over.'],
    'draft, the notes' => ['draft', 'notes', 'Add the notes once the event is published and over.'],
]);

test('the end after the change decides: an event that started and ends earlier takes a replay link at once', function () {
    $event = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create([
        'starts_at' => '2026-11-10T08:00:00Z',
        'ends_at' => '2026-11-10T10:00:00Z',
    ]);

    eafPatch('host', $event, ['ends_at' => '2026-11-10T09:30:00+01:00', 'replay_url' => EAF_OTHER])->assertOk();

    expect($event->fresh()->replay_url)->toBe(EAF_OTHER)->and($event->fresh()->isPast())->toBeTrue();
});

test('a published event that did not start cannot get an end in the past: cancel it instead', function (string $reader, int $status, ?string $message) {
    $event = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create();
    $before = [$event->starts_at->toIso8601String(), $event->ends_at->toIso8601String()];

    $response = eafPatch($reader, $event, ['starts_at' => '2026-11-03T17:00:00+01:00', 'ends_at' => '2026-11-03T18:00:00+01:00'])->assertStatus($status);
    if ($message !== null) {
        $response->assertJsonPath('message', $message);
    }

    $fresh = $event->fresh();
    expect([$fresh->starts_at->toIso8601String(), $fresh->ends_at->toIso8601String()])->toBe($before);
})->with([
    'the owner' => ['owner', 422, 'This event did not happen yet. Cancel it instead.'],
    'an admin' => ['admin', 422, 'This event did not happen yet. Cancel it instead.'],
    // The host never moves a start into the past: the field error of the start comes first
    'the host' => ['host', 400, null],
]);

test('an event that is over with a replay link or notes is not moved to a later time, unless they are removed', function (string $reader, string $field) {
    $this->event->forceFill($field === 'replay_url' ? ['replay_url' => EAF_OTHER] : ['notes' => 'Notes', 'notes_html' => '<p>Notes</p>'])->save();
    $later = ['starts_at' => '2026-11-20T17:00:00+01:00', 'ends_at' => '2026-11-20T18:00:00+01:00'];

    eafPatch($reader, $this->event, $later)
        ->assertStatus(422)
        ->assertJsonPath('message', 'Remove the replay link and the notes before you move this event to a later time.');
    expect($this->event->fresh()->isPast())->toBeTrue();

    // An earlier time keeps it over (the owner and the admins only enter a start in the past), and the move with the
    // removal passes
    if ($reader !== 'host') {
        eafPatch($reader, $this->event, ['starts_at' => '2026-11-02T17:00:00+01:00', 'ends_at' => '2026-11-02T18:00:00+01:00'])->assertOk();
    }
    eafPatch($reader, $this->event, [...$later, 'replay_url' => null, 'notes' => ''])->assertOk();
    expect($this->event->fresh()->isPast())->toBeFalse();
})->with(['the host' => ['host'], 'the owner' => ['owner'], 'an admin' => ['admin']])->with(['replay_url', 'notes']);

test('a cancelled event that is over with a replay link and notes is not moved to a later time either, unless they are removed', function () {
    $this->event->forceFill(['replay_url' => EAF_OTHER, 'notes' => 'Notes', 'notes_html' => '<p>Notes</p>'])->save();
    app(SetsEventStates::class)->setState($this->c['host'], $this->event, ['state' => 'cancelled']);
    $later = ['starts_at' => '2026-11-20T17:00:00+01:00', 'ends_at' => '2026-11-20T18:00:00+01:00'];

    eafPatch('host', $this->event, $later)
        ->assertStatus(422)
        ->assertJsonPath('message', 'Remove the replay link and the notes before you move this event to a later time.');
    expect($this->event->fresh()->isPast())->toBeTrue();

    eafPatch('host', $this->event, [...$later, 'replay_url' => null, 'notes' => ''])->assertOk();
    $fresh = $this->event->fresh();
    expect($fresh->isPast())->toBeFalse()->and($fresh->replay_url)->toBeNull()->and($fresh->notes)->toBe('');
});

test('the replay link and the notes are decided from the locked row of the event, not from the model of the caller', function () {
    $stale = EventModel::query()->findOrFail($this->event->id);
    // Another request cancelled the event after the caller read it
    EventModel::query()->whereKey($this->event->id)->update(['state' => 'cancelled']);
    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = strtolower($query->sql);
    });

    expect(fn () => app(UpdatesEvents::class)->update($this->c['host'], $stale, ['replay_url' => EAF_OTHER]))
        ->toThrow(ValidationException::class, 'Add the replay once the event is published and over.');

    expect(EventModel::query()->findOrFail($this->event->id)->replay_url)->toBeNull()
        ->and(collect($statements)->contains(fn (string $sql) => preg_match('/from "events" where .* for update$/', $sql) === 1))->toBeTrue();
});

test('the migration of the replay link and the notes goes down and up again', function () {
    $migration = require database_path('migrations/2026_11_23_210000_add_replay_and_notes_to_events.php');
    $this->event->forceFill(['replay_url' => EAF_OTHER, 'notes' => 'Notes'])->save();

    $migration->down();
    expect(Schema::hasColumn('events', 'replay_url'))->toBeFalse()
        ->and(Schema::hasColumn('events', 'notes'))->toBeFalse()
        ->and(Schema::hasColumn('events', 'notes_html'))->toBeFalse()
        ->and(EventModel::query()->whereKey($this->event->id)->exists())->toBeTrue();

    $migration->up();
    $row = DB::table('events')->where('id', $this->event->id)->first(['replay_url', 'notes', 'notes_html']);
    expect($row?->replay_url)->toBeNull()->and($row?->notes)->toBe('')->and($row?->notes_html)->toBe('');
});

test('an empty value clears the replay link or the notes, also on a cancelled event and on a draft', function (string $state, mixed $empty) {
    $factory = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->past();
    $event = match ($state) {
        'published' => $factory->create(['replay_url' => EAF_OTHER, 'notes' => 'Notes', 'notes_html' => '<p>Notes</p>']),
        'cancelled' => $factory->cancelled()->create(['replay_url' => EAF_OTHER, 'notes' => 'Notes', 'notes_html' => '<p>Notes</p>']),
        'draft' => $factory->draft()->create(['replay_url' => EAF_OTHER, 'notes' => 'Notes', 'notes_html' => '<p>Notes</p>']),
    };

    eafPatch('host', $event, ['replay_url' => $empty, 'notes' => $empty])->assertOk();

    $fresh = $event->fresh();
    expect($fresh->replay_url)->toBeNull()->and($fresh->notes)->toBe('')->and($fresh->notes_html)->toBe('');
})->with(['published', 'cancelled', 'draft'])->with(['null' => [null], 'an empty text' => ['']]);

test('the owner, an admin and the host set the replay, a moderator with the right to delete content and a member are refused', function (string $reader, int $status) {
    eafPatch($reader, $this->event, ['replay_url' => EAF_OTHER, 'notes' => 'Notes'])->assertStatus($status);

    expect($this->event->fresh()->replay_url)->toBe($status === 200 ? EAF_OTHER : null);
})->with([
    'the owner' => ['owner', 200],
    'an admin' => ['admin', 200],
    'the host' => ['host', 200],
    'a moderator' => ['moderator', 403],
    'a member' => ['member', 403],
]);

test('the replay link is an http or https address of at most 2048 characters, and the notes at most 20 000 characters', function (array $input, string $field) {
    eafPatch('host', $this->event, $input)->assertStatus(400)->assertJsonValidationErrors([$field]);
})->with([
    'another scheme' => [['replay_url' => 'ftp://example.com/recording'], 'replay_url'],
    'not an address' => [['replay_url' => 'the recording'], 'replay_url'],
    'too long' => [['replay_url' => 'https://example.com/'.str_repeat('a', 2030)], 'replay_url'],
    'notes too long' => [['notes' => str_repeat('a', 20001)], 'notes'],
]);

test('a change of the replay or the notes does not move the sequence, and sends space.event.updated', function () {
    $this->event->forceFill(['sequence' => 3])->save();

    eafPatch('host', $this->event, ['replay_url' => EAF_YOUTUBE, 'notes' => 'Notes'])->assertOk();

    expect($this->event->fresh()->sequence)->toBe(3);
    Event::assertDispatched(SpaceContentChanged::class, fn (SpaceContentChanged $sent) => $sent->broadcastAs() === SpaceContentChanged::EVENT_UPDATED);
});

test('the notes are rendered with the HTML escaped, a mention is a link that tells nobody, and an image of the notes is used', function () {
    $this->c['member']->forceFill(['username' => 'eaf-member'])->save();
    $upload = Upload::create([
        'community_id' => $this->c['community']->id, 'user_id' => $this->c['host']->id, 'disk' => 'public',
        'path' => sprintf('uploads/%s/2026/11/eafslide.png', $this->c['community']->cuid),
        'mime_type' => 'image/png', 'width' => 10, 'height' => 10, 'size_bytes' => 9, 'alt' => 'A slide',
    ]);
    $upload->forceFill(['cuid' => 'eafslide'])->save();

    eafPatch('host', $this->event, ['notes' => "The **slides** <script>alert(1)</script> for @eaf-member\n\n![A slide](".$upload->url().')'])->assertOk();

    $fresh = $this->event->fresh();
    expect($fresh->notes_html)->toContain('<strong>slides</strong>')
        ->not->toContain('<script>')
        ->and(DB::table('notifications')->count())->toBe(0)
        ->and(DB::table('mentions')->count())->toBe(0)
        ->and(DB::table('upload_usages')->where('event_id', $fresh->id)->where('upload_id', $upload->id)->exists())->toBeTrue();

    // The image goes out of use when the notes lose it
    eafPatch('host', $this->event, ['notes' => 'No image any more'])->assertOk();
    expect(DB::table('upload_usages')->where('event_id', $fresh->id)->where('upload_id', $upload->id)->exists())->toBeFalse();
});

test('a create and a duplicate take no replay link and no notes, and the copy of an event never takes them (rule 6)', function () {
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENTS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->space->cuid]);
    eafAs('admin');
    $created = $this->postJson($path, [
        'title' => 'A new event',
        'starts_at' => '2026-11-18T17:00:00+01:00',
        'ends_at' => '2026-11-18T18:00:00+01:00',
        'time_zone' => 'Europe/Brussels',
        'replay_url' => EAF_OTHER,
        'notes' => 'Ignored',
    ])->assertCreated();
    expect($created->json('data.replay_url'))->toBeNull()
        ->and($created->json('data.notes'))->toBe('')
        ->and($created->json('data.notes_html'))->toBe('');

    $this->event->forceFill(['replay_url' => EAF_OTHER, 'notes' => 'Notes', 'notes_html' => '<p>Notes</p>'])->save();
    $copy = app(DuplicatesEvents::class)->duplicate($this->c['admin'], $this->event);

    expect($copy->replay_url)->toBeNull()->and($copy->notes)->toBe('')->and($copy->notes_html)->toBe('')
        ->and($copy->fresh()->replay_url)->toBeNull()->and($copy->fresh()->notes)->toBe('');
});

test('content:render-again renders the notes of events again', function () {
    $this->event->forceFill(['notes' => 'The **slides**', 'notes_html' => '<p>old</p>'])->save();

    app(RendersContentAgain::class)->renderAgain();

    expect($this->event->fresh()->notes_html)->toContain('<strong>slides</strong>');
});

test('the update action takes the two fields with its contract', function () {
    app(UpdatesEvents::class)->update($this->c['host'], $this->event, ['replay_url' => EAF_VIMEO]);

    expect($this->event->fresh()->replay_url)->toBe(EAF_VIMEO);
});

// Who reads the replay (rules 27 to 30 and 50)
// ---------------------------------------------------------------------------------------------------------------------

/**
 * Every object of an event of the given cuids in a decoded answer
 *
 * @param  array<mixed>  $data
 * @param  list<string>  $cuids
 * @return list<array<string, mixed>>
 */
function eafEventsIn(array $data, array $cuids): array
{
    $found = [];
    if (isset($data['cuid'], $data['starts_at']) && in_array($data['cuid'], $cuids, true)) {
        $found[] = $data;
    }
    foreach ($data as $value) {
        if (is_array($value)) {
            $found = [...$found, ...eafEventsIn($value, $cuids)];
        }
    }

    return $found;
}

/**
 * The decoded answer of a route for a reader: the JSON of the API, or the props of a page
 *
 * @return array<mixed>|null null when the route does not answer this reader
 */
function eafRead(string $reader, string $route, EventModel $past, EventModel $future): ?array
{
    $test = test();
    $community = $test->c['community'];
    $space = $past->space;
    $base = [ApiRoutes::PARAM_COMMUNITY => $community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid];
    $slugs = ['communitySlug' => $community->slug, 'spaceSlug' => $space->slug];
    eafAs($reader);

    $response = match ($route) {
        'space, upcoming' => $test->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENTS, $base).'?scope=upcoming'),
        'space, past' => $test->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENTS, $base).'?scope=past'),
        'community' => $test->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_EVENTS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid])),
        'one event' => $test->getJson(eafPath($past)),
        'one future event' => $test->getJson(eafPath($future)),
        'my events, upcoming' => $test->getJson(ApiRoutes::path(ApiRoutes::USER_EVENTS).'?scope=upcoming'),
        'my events, past' => $test->getJson(ApiRoutes::path(ApiRoutes::USER_EVENTS).'?scope=past'),
        'the event page' => $test->get(route('events.show', [...$slugs, 'eventSlug' => $past->slug])),
        'the space page' => $test->get(route('spaces.show', $slugs).'?scope=past'),
        'the form' => $test->get(route('events.edit', [...$slugs, 'eventSlug' => $past->slug])),
    };
    if ($response->status() !== 200) {
        return null;
    }

    return str_starts_with((string) $response->headers->get('Content-Type'), 'application/json')
        ? (array) $response->json()
        : (array) ($response->viewData('page')['props'] ?? []);
}

test('the replay link reaches the members who can see the event and nobody else, on every route that carries an event', function (string $visibility, string $url, bool $player) {
    $make = fn (array $state) => EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create([
        'location_visibility' => $visibility === 'going' ? KnowiiEventLocationVisibility::Going : KnowiiEventLocationVisibility::Members,
        'replay_expected' => true,
        'replay_url' => $url,
        'notes' => 'EAF-NOTES-OF-THE-HOST',
        'notes_html' => '<p>EAF-NOTES-OF-THE-HOST</p>',
        ...$state,
    ]);
    // The replay is set after the end; a future event with the column filled checks the cut of the lists of upcoming events
    $past = $make(['starts_at' => now()->subWeek(), 'ends_at' => now()->subWeek()->addHour()]);
    $future = $make([]);
    foreach ([$past, $future] as $event) {
        EventAnswer::factory()->to($event)->of($this->c['yes'])->yes()->create();
        EventAnswer::factory()->to($event)->of($this->c['replay'])->replay()->create();
        EventAnswer::factory()->to($event)->of($this->c['no'])->no()->create();
    }
    $cuids = [$past->cuid, $future->cuid];
    $routes = ['space, upcoming', 'space, past', 'community', 'one event', 'one future event', 'my events, upcoming', 'my events, past', 'the event page', 'the space page', 'the form'];

    foreach (EAF_READERS as $reader) {
        $allowed = ! in_array($reader, ['visitor', 'outsider'], true);
        foreach ($routes as $route) {
            $data = eafRead($reader, $route, $past, $future);
            if ($data === null) {
                continue;
            }
            $label = "{$reader}, {$route}";
            $events = eafEventsIn($data, $cuids);
            foreach ($events as $event) {
                expect($event['can_view_replay'])->toBe($allowed, $label)
                    ->and($event['replay_url'])->toBe($allowed ? $url : null, $label)
                    ->and($event['replay'] !== null)->toBe($allowed && $player, $label);
                if ($allowed && $player) {
                    expect($event['replay']['url'])->toBe($url, $label);
                }
            }
            // Nothing else in the answer holds the address
            $text = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (! $allowed) {
                expect(str_contains((string) $text, $url))->toBeFalse($label);
            }
            // The notes are for every reader of the event, with the description
            if (in_array($route, ['one event', 'the event page'], true)) {
                expect($events)->not->toBeEmpty($label)
                    ->and($events[0]['notes'])->toBe('EAF-NOTES-OF-THE-HOST', $label);
            }
        }
    }
})->with([
    'a going event' => ['going'],
    'a members event' => ['members'],
])->with([
    'a link of the first video host' => [EAF_YOUTUBE, true],
    'a link of the second video host' => [EAF_VIMEO, true],
    'a link of another place' => [EAF_OTHER, false],
]);

test('a visitor and a person who is not a member learn nothing of a replay, and read the notes', function (string $reader) {
    $this->event->forceFill(['replay_url' => EAF_YOUTUBE, 'notes' => 'EAF-NOTES', 'notes_html' => '<p>EAF-NOTES</p>'])->save();
    eafAs($reader);

    $data = $this->getJson(eafPath($this->event))->assertOk()->json('data');

    expect($data['can_view_replay'])->toBeFalse()
        ->and($data['replay_url'])->toBeNull()
        ->and($data['replay'])->toBeNull()
        ->and($data['notes'])->toBe('EAF-NOTES')
        ->and($data['notes_html'])->toBe('<p>EAF-NOTES</p>');
})->with(['visitor', 'outsider']);

test('the lists carry no notes, as they carry no description', function () {
    $this->event->forceFill(['notes' => 'EAF-NOTES', 'notes_html' => '<p>EAF-NOTES</p>'])->save();
    eafAs('member');

    $data = $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENTS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->space->cuid]).'?scope=past')->assertOk()->json('data.0');

    expect($data['notes'])->toBeNull()->and($data['notes_html'])->toBeNull();
});

test('the calendar file holds no replay and no notes (rule 30)', function () {
    $this->event->forceFill(['replay_url' => EAF_OTHER, 'notes' => 'EAF-NOTES-IN-NO-FILE', 'notes_html' => '<p>EAF-NOTES-IN-NO-FILE</p>'])->save();
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENT_CALENDAR_FILE, [
        ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid,
        ApiRoutes::PARAM_SPACE => $this->space->cuid,
        ApiRoutes::PARAM_EVENT => $this->event->cuid,
    ]);

    foreach (['host', 'member'] as $reader) {
        $file = (string) $this->actingAs(User::query()->findOrFail($this->c[$reader]->id))->get($path, ['Accept' => 'text/html'])->assertOk()->getContent();
        $file = str_replace("\r\n ", '', $file);

        expect(str_contains($file, 'replays.example.com'))->toBeFalse($reader)
            ->and(str_contains($file, 'EAF-NOTES-IN-NO-FILE'))->toBeFalse($reader);
    }
});
