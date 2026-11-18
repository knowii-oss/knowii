<?php

use App\Contracts\Events\CreatesEvents;
use App\Contracts\Events\DeletesEvents;
use App\Contracts\Events\DuplicatesEvents;
use App\Contracts\Events\SetsEventStates;
use App\Contracts\Events\UpdatesEvents;
use App\Contracts\Notifications\ChoosesSpaceLevels;
use App\Contracts\Spaces\UpdatesSpaces;
use App\Contracts\Uploads\UploadsImages;
use App\Contracts\Users\DeletesAccounts;
use App\Contracts\Users\DeletesMemberContent;
use App\Contracts\Users\ExportsUsers;
use App\Enums\KnowiiEventLocationVisibility;
use App\Enums\KnowiiEventState;
use App\Enums\KnowiiSpaceNotificationLevel;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceContentChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\BusinessException;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\Event as EventModel;
use App\Models\Notification;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use App\Models\User;
use App\Support\EventTimes;
use App\Support\SpaceNotificationLevels;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
 * Events, slice E1a (#1513, ADR 0053): the space type, the actions, the times, the states and the uploads.
 */

beforeEach(function () {
    // A fixed now: 10 November 2026, 09:00 UTC. The events of the tests are later
    $this->travelTo(CarbonImmutable::parse('2026-11-10T09:00:00Z'));
    Event::fake([SpaceTreeChanged::class]);
    Storage::fake('public');
    $this->c = communityWithMembers();
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    // Where the team plans the events, and where every member may plan one
    $this->events = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    $this->open = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'posting_policy' => KnowiiSpacePostingPolicy::Members]);
});

/**
 * A valid input: 18 November 2026, 17:00 to 18:00 in Brussels (UTC+1)
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function evtInput(array $changes = []): array
{
    return [
        'title' => 'Weekly review office hour',
        'body' => 'Bring your **questions**',
        'starts_at' => '2026-11-18T17:00:00+01:00',
        'ends_at' => '2026-11-18T18:00:00+01:00',
        'time_zone' => 'Europe/Brussels',
        ...$changes,
    ];
}

/**
 * @param  array<string, mixed>  $changes
 */
function evtCreate(User $user, Space $space, array $changes = []): EventModel
{
    return app(CreatesEvents::class)->create($user, $space, evtInput($changes));
}

/**
 * The messages of a refused validation, by field
 *
 * @return array<string, array<int, string>>
 */
function evtErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('The validation passed');
}

function evtImage(User $user, Community $community): Upload
{
    return app(UploadsImages::class)->upload($user, $community, [
        'file' => UploadedFile::fake()->image('cover.jpg', 40, 40),
        'alt' => 'A cover',
    ]);
}

// ---------------------------------------------------------------------------------------------------------------------
// The space type
// ---------------------------------------------------------------------------------------------------------------------

test('a space of the type events exists, and its default level is Everything whoever posts (#1513)', function () {
    expect($this->events->type)->toBe(KnowiiSpaceType::Events)
        ->and(SpaceNotificationLevels::builtInDefault($this->events))->toBe(KnowiiSpaceNotificationLevel::All)
        ->and(SpaceNotificationLevels::builtInDefault($this->open))->toBe(KnowiiSpaceNotificationLevel::All);
});

test('a member may choose Everything for an events space, and a manager may set Everything or Only what is for me as its default', function () {
    $level = app(ChoosesSpaceLevels::class)->set($this->c['member'], $this->events, ['level' => 'all']);
    expect($level['level'])->toBe('all');

    app(UpdatesSpaces::class)->update($this->c['owner'], $this->events, ['default_notification_level' => 'mine']);
    expect($this->events->refresh()->default_notification_level)->toBe(KnowiiSpaceNotificationLevel::Mine);
    app(UpdatesSpaces::class)->update($this->c['owner'], $this->events, ['default_notification_level' => 'all']);
    expect($this->events->refresh()->default_notification_level)->toBe(KnowiiSpaceNotificationLevel::All);

    // A page space still has no Everything
    $pages = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Pages]);
    expect(fn () => app(ChoosesSpaceLevels::class)->set($this->c['member'], $pages, ['level' => 'all']))->toThrow(BusinessException::class)
        ->and(fn () => app(UpdatesSpaces::class)->update($this->c['owner'], $pages, ['default_notification_level' => 'all']))->toThrow(ValidationException::class);
});

test('the type of an events space never changes', function () {
    app(UpdatesSpaces::class)->update($this->c['owner'], $this->events, ['type' => 'discussion', 'name' => 'Office hours']);

    expect($this->events->refresh()->type)->toBe(KnowiiSpaceType::Events);
});

// ---------------------------------------------------------------------------------------------------------------------
// Create
// ---------------------------------------------------------------------------------------------------------------------

test('a moderator creates a draft: UTC instants, the zone as given, the creator as host, a slug of the space', function () {
    $event = evtCreate($this->c['moderator'], $this->events);

    expect($event->state)->toBe(KnowiiEventState::Draft)
        ->and($event->host_id)->toBe($this->c['moderator']->id)
        ->and($event->starts_at->toIso8601ZuluString())->toBe('2026-11-18T16:00:00Z')
        ->and($event->ends_at->toIso8601ZuluString())->toBe('2026-11-18T17:00:00Z')
        ->and($event->time_zone)->toBe('Europe/Brussels')
        ->and($event->slug)->toBe('weekly-review-office-hour')
        ->and($event->sequence)->toBe(0)
        ->and($event->location_visibility)->toBe(KnowiiEventLocationVisibility::Members)
        ->and($event->body_html)->toBe('<p>Bring your <strong>questions</strong></p>')
        ->and($event->published_notified_at)->toBeNull();

    // The slug is unique in its space, and another space may hold the same slug
    expect(evtCreate($this->c['moderator'], $this->events)->slug)->toBe('weekly-review-office-hour-2')
        ->and(evtCreate($this->c['member'], $this->open)->slug)->toBe('weekly-review-office-hour');
});

test('the posting policy of the space decides who creates an event', function () {
    expect(fn () => evtCreate($this->c['member'], $this->events))->toThrow(AuthorizationException::class)
        ->and(fn () => evtCreate($this->c['outsider'], $this->open))->toThrow(AuthorizationException::class);

    expect(evtCreate($this->c['member'], $this->open)->host_id)->toBe($this->c['member']->id)
        ->and(evtCreate($this->c['admin'], $this->events)->host_id)->toBe($this->c['admin']->id);
});

test('an event is refused in a space of another type', function () {
    $discussion = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Discussion]);

    expect(fn () => evtCreate($this->c['owner'], $discussion))->toThrow(BusinessException::class, 'Events can only be created in events spaces');
});

test('the description is optional, its raw HTML is escaped, and the title has 3 to 200 characters', function () {
    $event = evtCreate($this->c['owner'], $this->events, ['body' => null]);
    expect($event->body)->toBe('')->and($event->body_html)->toBe('');

    $escaped = evtCreate($this->c['owner'], $this->events, ['body' => 'Hi <script>alert(1)</script>']);
    expect($escaped->body_html)->toBe('<p>Hi &lt;script&gt;alert(1)&lt;/script&gt;</p>');

    expect(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['title' => 'No'])))->toHaveKey('title')
        ->and(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['title' => str_repeat('a', 201)])))->toHaveKey('title')
        ->and(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['body' => str_repeat('a', 20001)])))->toHaveKey('body');
});

test('the meeting address is http or https, the place has 255 characters at most, and both may be empty when published', function () {
    expect(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['meeting_url' => 'javascript:alert(1)'])))->toHaveKey('meeting_url')
        ->and(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['meeting_url' => 'ftp://example.com/room'])))->toHaveKey('meeting_url')
        ->and(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['place' => str_repeat('a', 256)])))->toHaveKey('place')
        ->and(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['location_visibility' => 'everyone'])))->toHaveKey('location_visibility');

    $event = evtCreate($this->c['owner'], $this->events, ['state' => 'published', 'meeting_url' => '', 'place' => '   ']);
    expect($event->state)->toBe(KnowiiEventState::Published)->and($event->meeting_url)->toBeNull()->and($event->place)->toBeNull();

    $going = evtCreate($this->c['owner'], $this->events, ['meeting_url' => 'https://meet.example.com/abc', 'place' => 'Room 2', 'location_visibility' => 'going']);
    expect($going->meeting_url)->toBe('https://meet.example.com/abc')->and($going->place)->toBe('Room 2')
        ->and($going->location_visibility)->toBe(KnowiiEventLocationVisibility::Going);
});

test('the state of a new event is draft or published, nothing else', function () {
    expect(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['state' => 'cancelled'])))->toHaveKey('state');
});

// ---------------------------------------------------------------------------------------------------------------------
// Times
// ---------------------------------------------------------------------------------------------------------------------

test('a value without an offset, or that is not ISO 8601, is a field error', function (string $value) {
    expect(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['starts_at' => $value])))->toBe(['starts_at' => [EventTimes::FORMAT_MESSAGE]]);
})->with([
    'no offset' => '2026-11-18T17:00:00',
    'a date only' => '2026-11-18',
    'words' => 'next Tuesday',
    'a day that does not exist' => '2026-11-31T17:00:00+01:00',
]);

test('an offset that is not the offset of the zone at that time is a field error on its field', function () {
    // Brussels is at UTC+1 in November
    expect(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['starts_at' => '2026-11-18T17:00:00+02:00'])))->toBe(['starts_at' => [EventTimes::OFFSET_MESSAGE]])
        ->and(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['ends_at' => '2026-11-18T18:00:00Z'])))->toBe(['ends_at' => [EventTimes::OFFSET_MESSAGE]]);

    // The same moment in New York time, with the zone of New York
    $event = evtCreate($this->c['owner'], $this->events, ['starts_at' => '2026-11-18T11:00:00-05:00', 'ends_at' => '2026-11-18T12:00:00-05:00', 'time_zone' => 'America/New_York']);
    expect($event->starts_at->toIso8601ZuluString())->toBe('2026-11-18T16:00:00Z')->and($event->time_zone)->toBe('America/New_York');
});

test('an unknown zone is a field error', function () {
    expect(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['time_zone' => 'Europe/Atlantis'])))->toHaveKey('time_zone')
        ->and(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['time_zone' => 'CET+1'])))->toHaveKey('time_zone');
});

test('a time that does not exist in the zone gets the two hours around the gap, from the zone data', function (string $offset) {
    // 28 March 2027: the clocks of Brussels go from 02:00 to 03:00
    $errors = evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['starts_at' => "2027-03-28T02:30:00{$offset}", 'ends_at' => '2027-03-28T04:00:00+02:00']));

    expect($errors)->toBe(['starts_at' => ['This time does not exist in this zone. Choose 01:59 or earlier, or 03:00 or later.']]);
})->with(['+01:00', '+02:00']);

test('the gap of another zone gives its own hours', function () {
    // 14 March 2027: the clocks of New York go from 02:00 to 03:00 (from UTC-5 to UTC-4)
    $errors = evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['starts_at' => '2027-03-14T02:15:00-05:00', 'ends_at' => '2027-03-14T05:00:00-04:00', 'time_zone' => 'America/New_York']));

    expect($errors['starts_at'][0])->toBe('This time does not exist in this zone. Choose 01:59 or earlier, or 03:00 or later.');
});

test('a time that exists twice is accepted with either real offset, and the offset decides the instant', function () {
    // 31 October 2027: the clocks of Brussels go back from 03:00 to 02:00
    $summer = evtCreate($this->c['owner'], $this->events, ['starts_at' => '2027-10-31T02:30:00+02:00', 'ends_at' => '2027-10-31T04:00:00+01:00']);
    $winter = evtCreate($this->c['owner'], $this->events, ['starts_at' => '2027-10-31T02:30:00+01:00', 'ends_at' => '2027-10-31T04:00:00+01:00']);

    expect($summer->starts_at->toIso8601ZuluString())->toBe('2027-10-31T00:30:00Z')
        ->and($winter->starts_at->toIso8601ZuluString())->toBe('2027-10-31T01:30:00Z');
});

test('the end is after the start and at most 24 hours later', function () {
    expect(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['ends_at' => '2026-11-18T17:00:00+01:00'])))->toBe(['ends_at' => ['The end must be after the start']])
        ->and(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['ends_at' => '2026-11-19T17:01:00+01:00'])))->toBe(['ends_at' => ['An event lasts 24 hours at most']]);

    expect(evtCreate($this->c['owner'], $this->events, ['ends_at' => '2026-11-19T17:00:00+01:00'])->ends_at->toIso8601ZuluString())->toBe('2026-11-19T16:00:00Z');
});

test('a start in the past is for the owner and the admins only (to enter past events)', function () {
    $past = ['starts_at' => '2026-11-03T17:00:00+01:00', 'ends_at' => '2026-11-03T18:00:00+01:00'];

    expect(evtErrors(fn () => evtCreate($this->c['moderator'], $this->events, $past)))->toBe(['starts_at' => ['Choose a start in the future']])
        ->and(evtErrors(fn () => evtCreate($this->c['member'], $this->open, $past)))->toHaveKey('starts_at');

    $event = evtCreate($this->c['admin'], $this->events, $past);
    expect($event->isPast())->toBeTrue()
        ->and(evtCreate($this->c['owner'], $this->events, $past)->hasStarted())->toBeTrue();
});

// ---------------------------------------------------------------------------------------------------------------------
// Host and cover
// ---------------------------------------------------------------------------------------------------------------------

test('a creator may name another member as host, and then has no right of a host', function () {
    $event = evtCreate($this->c['member'], $this->open, ['host_cuid' => $this->c['moderator']->cuid]);

    expect($event->host_id)->toBe($this->c['moderator']->id)
        ->and(Gate::forUser($this->c['member'])->allows('update', $event))->toBeFalse()
        ->and(Gate::forUser($this->c['moderator'])->allows('update', $event))->toBeTrue();
});

test('the host is a member of the community who can read the space: anything else is a field error', function () {
    $other = User::factory()->withUserProfile()->create();
    $groups = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $groups->accessGroups()->attach(AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Buyers']));

    expect(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['host_cuid' => $this->c['outsider']->cuid])))->toBe(['host_cuid' => ['Choose a member of the community who can read this space']])
        ->and(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['host_cuid' => 'nobody'])))->toHaveKey('host_cuid')
        ->and(evtErrors(fn () => evtCreate($this->c['owner'], $groups, ['host_cuid' => $this->c['member']->cuid])))->toHaveKey('host_cuid')
        ->and(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['host_cuid' => $other->cuid])))->toHaveKey('host_cuid');
});

test('the cover is an upload of the same community, and null removes it', function () {
    $cover = evtImage($this->c['owner'], $this->c['community']);
    $foreign = evtImage($this->c['outsider'], Community::factory()->create(['owner_id' => $this->c['outsider']->id]));

    $event = evtCreate($this->c['owner'], $this->events, ['cover_upload_cuid' => $cover->cuid, 'cover_alt' => 'The team at work']);
    expect($event->cover_upload_id)->toBe($cover->id)->and($event->cover_alt)->toBe('The team at work');

    expect(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['cover_upload_cuid' => $foreign->cuid])))->toBe(['cover_upload_cuid' => ['Choose an image uploaded in this community']])
        ->and(evtErrors(fn () => evtCreate($this->c['owner'], $this->events, ['cover_alt' => str_repeat('a', 126)])))->toHaveKey('cover_alt');

    app(UpdatesEvents::class)->update($this->c['owner'], $event, ['cover_upload_cuid' => null, 'cover_alt' => '']);
    expect($event->refresh()->cover_upload_id)->toBeNull()->and($event->cover_alt)->toBeNull();
});

// ---------------------------------------------------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------------------------------------------------

test('the host changes the event; the slug stays; sequence goes up only for the time, the address and the place', function () {
    $event = evtCreate($this->c['moderator'], $this->events);
    $update = fn (array $input) => app(UpdatesEvents::class)->update($this->c['moderator'], $event, $input);

    $update(['title' => 'A new title', 'body' => 'New text']);
    expect($event->refresh()->title)->toBe('A new title')
        ->and($event->slug)->toBe('weekly-review-office-hour')
        ->and($event->body_html)->toBe('<p>New text</p>')
        ->and($event->sequence)->toBe(0);

    $update(['meeting_url' => 'https://meet.example.com/new']);
    expect($event->refresh()->sequence)->toBe(1);
    $update(['place' => 'Room 2']);
    expect($event->refresh()->sequence)->toBe(2);
    $update(['starts_at' => '2026-11-18T17:30:00+01:00']);
    expect($event->refresh()->sequence)->toBe(3)->and($event->starts_at->toIso8601ZuluString())->toBe('2026-11-18T16:30:00Z');
    $update(['ends_at' => '2026-11-18T19:00:00+01:00']);
    expect($event->refresh()->sequence)->toBe(4);
    // The same values again change nothing
    $update(['place' => 'Room 2', 'starts_at' => '2026-11-18T17:30:00+01:00']);
    expect($event->refresh()->sequence)->toBe(4);
    $update(['location_visibility' => 'going']);
    expect($event->refresh()->sequence)->toBe(4)->and($event->location_visibility)->toBe(KnowiiEventLocationVisibility::Going);
});

test('an update checks the times with the stored values of the fields that are absent', function () {
    $event = evtCreate($this->c['moderator'], $this->events);

    expect(evtErrors(fn () => app(UpdatesEvents::class)->update($this->c['moderator'], $event, ['ends_at' => '2026-11-18T16:00:00+01:00'])))->toBe(['ends_at' => ['The end must be after the start']])
        ->and(evtErrors(fn () => app(UpdatesEvents::class)->update($this->c['moderator'], $event, ['starts_at' => '2026-11-17T17:00:00+01:00'])))->toBe(['ends_at' => ['An event lasts 24 hours at most']])
        // The offset is checked against the stored zone, or the new one
        ->and(evtErrors(fn () => app(UpdatesEvents::class)->update($this->c['moderator'], $event, ['starts_at' => '2026-11-18T11:00:00-05:00'])))->toHaveKey('starts_at');

    app(UpdatesEvents::class)->update($this->c['moderator'], $event, ['starts_at' => '2026-11-18T11:00:00-05:00', 'time_zone' => 'America/New_York']);
    expect($event->refresh()->starts_at->toIso8601ZuluString())->toBe('2026-11-18T16:00:00Z')->and($event->time_zone)->toBe('America/New_York');

    // A new zone alone never moves the moment
    app(UpdatesEvents::class)->update($this->c['moderator'], $event, ['time_zone' => 'Asia/Singapore']);
    expect($event->refresh()->starts_at->toIso8601ZuluString())->toBe('2026-11-18T16:00:00Z')->and($event->sequence)->toBe(0);
});

test('a new start in the past is for the owner and the admins only; an unchanged start in the past is not checked', function () {
    $event = EventModel::factory()->inSpace($this->events)->hostedBy($this->c['moderator'])->past()->create();

    expect(evtErrors(fn () => app(UpdatesEvents::class)->update($this->c['moderator'], $event, ['starts_at' => '2026-11-02T17:00:00+01:00', 'ends_at' => '2026-11-02T18:00:00+01:00'])))->toHaveKey('starts_at');

    app(UpdatesEvents::class)->update($this->c['moderator'], $event, ['title' => 'Notes of the last session']);
    expect($event->refresh()->title)->toBe('Notes of the last session');

    app(UpdatesEvents::class)->update($this->c['admin'], $event, ['starts_at' => '2026-11-02T17:00:00+01:00', 'ends_at' => '2026-11-02T18:00:00+01:00']);
    expect($event->refresh()->starts_at->toIso8601ZuluString())->toBe('2026-11-02T16:00:00Z');
});

test('only the owner, the admins and the host change an event: a moderator does not edit the events of others', function () {
    $event = evtCreate($this->c['admin'], $this->events, ['state' => 'published']);

    expect(fn () => app(UpdatesEvents::class)->update($this->c['moderator'], $event, ['title' => 'Taken over']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdatesEvents::class)->update($this->c['member'], $event, ['title' => 'Taken over']))->toThrow(AuthorizationException::class);

    app(UpdatesEvents::class)->update($this->c['owner'], $event, ['title' => 'By the owner', 'host_cuid' => $this->c['member']->cuid]);
    expect($event->refresh()->title)->toBe('By the owner')->and($event->host_id)->toBe($this->c['member']->id);
    expect(evtErrors(fn () => app(UpdatesEvents::class)->update($this->c['owner'], $event, ['host_cuid' => $this->c['outsider']->cuid])))->toHaveKey('host_cuid');
});

// ---------------------------------------------------------------------------------------------------------------------
// States
// ---------------------------------------------------------------------------------------------------------------------

test('each allowed change of state works and adds 1 to sequence; the same state again changes nothing', function () {
    $event = evtCreate($this->c['moderator'], $this->events);
    $set = fn (string $state) => app(SetsEventStates::class)->setState($this->c['moderator'], $event, ['state' => $state]);

    $set('published');
    expect($event->refresh()->state)->toBe(KnowiiEventState::Published)->and($event->sequence)->toBe(1);
    $set('draft');
    expect($event->refresh()->state)->toBe(KnowiiEventState::Draft)->and($event->sequence)->toBe(2);
    $set('published');
    $set('cancelled');
    expect($event->refresh()->state)->toBe(KnowiiEventState::Cancelled)->and($event->sequence)->toBe(4);
    $set('published');
    expect($event->refresh()->state)->toBe(KnowiiEventState::Published)->and($event->sequence)->toBe(5);

    $set('published');
    expect($event->refresh()->sequence)->toBe(5);
});

test('the other changes of state are refused with a reason', function () {
    $draft = evtCreate($this->c['moderator'], $this->events);
    $cancelled = EventModel::factory()->inSpace($this->events)->hostedBy($this->c['moderator'])->cancelled()->create();

    expect(fn () => app(SetsEventStates::class)->setState($this->c['moderator'], $draft, ['state' => 'cancelled']))->toThrow(BusinessException::class, 'A draft cannot be cancelled. Delete it instead.')
        ->and(fn () => app(SetsEventStates::class)->setState($this->c['moderator'], $cancelled, ['state' => 'draft']))->toThrow(BusinessException::class, 'A cancelled event cannot go back to draft. Publish it again instead.')
        ->and(fn () => app(SetsEventStates::class)->setState($this->c['moderator'], $draft, ['state' => 'past']))->toThrow(ValidationException::class);

    expect($draft->refresh()->sequence)->toBe(0)->and($cancelled->refresh()->sequence)->toBe(0);
});

test('a published event that started cannot go back to draft', function () {
    $event = EventModel::factory()->inSpace($this->events)->hostedBy($this->c['moderator'])->create(['starts_at' => now()->subMinutes(10), 'ends_at' => now()->addMinutes(50)]);

    expect(fn () => app(SetsEventStates::class)->setState($this->c['moderator'], $event, ['state' => 'draft']))
        ->toThrow(BusinessException::class, 'An event that started cannot go back to draft. Cancel it instead.');

    app(SetsEventStates::class)->setState($this->c['moderator'], $event, ['state' => 'cancelled']);
    expect($event->refresh()->state)->toBe(KnowiiEventState::Cancelled);
});

test('a moderator of the space cancels the event of another and does not publish it or put it back to draft', function () {
    $event = EventModel::factory()->inSpace($this->events)->hostedBy($this->c['admin'])->create();

    expect(fn () => app(SetsEventStates::class)->setState($this->c['moderator'], $event, ['state' => 'draft']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SetsEventStates::class)->setState($this->c['member'], $event, ['state' => 'cancelled']))->toThrow(AuthorizationException::class);

    app(SetsEventStates::class)->setState($this->c['moderator'], $event, ['state' => 'cancelled']);
    expect($event->refresh()->state)->toBe(KnowiiEventState::Cancelled);
    expect(fn () => app(SetsEventStates::class)->setState($this->c['moderator'], $event, ['state' => 'published']))->toThrow(AuthorizationException::class);
});

// ---------------------------------------------------------------------------------------------------------------------
// Duplicate and delete
// ---------------------------------------------------------------------------------------------------------------------

test('duplicate keeps the wall-clock time of the zone over the change of 28 March 2027, not the number of hours', function () {
    $cover = evtImage($this->c['owner'], $this->c['community']);
    // Tuesday 23 March 2027, 17:00 in Brussels (UTC+1): 16:00 UTC
    $event = evtCreate($this->c['moderator'], $this->events, [
        'starts_at' => '2027-03-23T17:00:00+01:00',
        'ends_at' => '2027-03-23T18:00:00+01:00',
        'meeting_url' => 'https://meet.example.com/abc',
        'place' => 'Room 2',
        'location_visibility' => 'going',
        'cover_upload_cuid' => $cover->cuid,
        'cover_alt' => 'The team',
        'state' => 'published',
    ]);

    $copy = app(DuplicatesEvents::class)->duplicate($this->c['moderator'], $event);

    // 30 March 2027, 17:00 in Brussels, now UTC+2: 15:00 UTC
    expect($copy->starts_at->toIso8601ZuluString())->toBe('2027-03-30T15:00:00Z')
        ->and($copy->ends_at->toIso8601ZuluString())->toBe('2027-03-30T16:00:00Z')
        ->and($copy->starts_at->copy()->setTimezone('Europe/Brussels')->format('H:i'))->toBe('17:00')
        ->and($copy->starts_at->copy()->setTimezone('Europe/Brussels')->dayOfWeek)->toBe($event->starts_at->copy()->setTimezone('Europe/Brussels')->dayOfWeek)
        ->and($copy->state)->toBe(KnowiiEventState::Draft)
        ->and($copy->sequence)->toBe(0)
        ->and($copy->published_notified_at)->toBeNull()
        ->and($copy->space_id)->toBe($event->space_id)
        ->and($copy->host_id)->toBe($event->host_id)
        ->and($copy->title)->toBe($event->title)
        ->and($copy->body)->toBe($event->body)
        ->and($copy->time_zone)->toBe('Europe/Brussels')
        ->and($copy->meeting_url)->toBe('https://meet.example.com/abc')
        ->and($copy->place)->toBe('Room 2')
        ->and($copy->location_visibility)->toBe(KnowiiEventLocationVisibility::Going)
        ->and($copy->cover_upload_id)->toBe($cover->id)
        ->and($copy->cover_alt)->toBe('The team')
        ->and($copy->slug)->not->toBe($event->slug)
        ->and($cover->usages()->where('event_id', $copy->id)->exists())->toBeTrue();

    expect(fn () => app(DuplicatesEvents::class)->duplicate($this->c['member'], $event))->toThrow(AuthorizationException::class);
});

test('a host who may not create events in the space cannot duplicate', function () {
    // The owner names the member as host in a space where only moderators post
    $event = evtCreate($this->c['owner'], $this->events, ['host_cuid' => $this->c['member']->cuid, 'state' => 'published']);

    expect(Gate::forUser($this->c['member'])->allows('update', $event))->toBeTrue()
        ->and(fn () => app(DuplicatesEvents::class)->duplicate($this->c['member'], $event))->toThrow(AuthorizationException::class);
});

test('a copy goes through the time rules of a create', function () {
    // 21 March 2027, 02:30 in Brussels exists; on 28 March the clocks skip from 02:00 to 03:00
    $skipped = evtCreate($this->c['moderator'], $this->events, ['starts_at' => '2027-03-21T02:30:00+01:00', 'ends_at' => '2027-03-21T04:00:00+01:00']);
    expect(evtErrors(fn () => app(DuplicatesEvents::class)->duplicate($this->c['moderator'], $skipped)))
        ->toBe(['starts_at' => ['This time does not exist in this zone. Choose 01:59 or earlier, or 03:00 or later.']]);

    // 01:30 to 03:30 lasts two hours on 21 March, and one hour on 28 March
    $shorter = evtCreate($this->c['moderator'], $this->events, ['starts_at' => '2027-03-21T01:30:00+01:00', 'ends_at' => '2027-03-21T03:30:00+01:00']);
    expect(evtErrors(fn () => app(DuplicatesEvents::class)->duplicate($this->c['moderator'], $shorter)))
        ->toBe(['ends_at' => ['The clocks change during the copy, so it would not last as long. Create this event by hand.']]);

    // A copy that starts in the past: for the owner and the admins only
    $old = EventModel::factory()->inSpace($this->events)->hostedBy($this->c['moderator'])->create(['starts_at' => now()->subWeeks(2), 'ends_at' => now()->subWeeks(2)->addHour()]);
    expect(evtErrors(fn () => app(DuplicatesEvents::class)->duplicate($this->c['moderator'], $old)))->toBe(['starts_at' => ['Choose a start in the future']]);
    expect(app(DuplicatesEvents::class)->duplicate($this->c['admin'], $old)->state)->toBe(KnowiiEventState::Draft);
    expect(EventModel::query()->where('space_id', $this->events->id)->count())->toBe(4);
});

test('a copy keeps a host who may still host, else the person who duplicates becomes the host', function () {
    $kept = evtCreate($this->c['owner'], $this->open, ['host_cuid' => $this->c['member']->cuid]);
    expect(app(DuplicatesEvents::class)->duplicate($this->c['owner'], $kept)->host_id)->toBe($this->c['member']->id);

    $this->c['community']->removeUser($this->c['member']);
    expect(app(DuplicatesEvents::class)->duplicate($this->c['owner'], $kept->refresh())->host_id)->toBe($this->c['owner']->id);

    $orphan = EventModel::factory()->inSpace($this->open)->hostedBy(null)->create();
    expect(app(DuplicatesEvents::class)->duplicate($this->c['admin'], $orphan)->host_id)->toBe($this->c['admin']->id);
});

test('the export of a member holds the events they host', function () {
    $event = evtCreate($this->c['member'], $this->open, ['state' => 'published']);
    evtCreate($this->c['admin'], $this->open, ['title' => 'Hosted by the admin']);

    $zip = new ZipArchive;
    expect($zip->open(app(ExportsUsers::class)->export($this->c['member'])))->toBeTrue();
    $events = json_decode((string) $zip->getFromName('events.json'), true)['events'];
    $readme = (string) $zip->getFromName('README.txt');
    $zip->close();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toMatchArray([
            'cuid' => $event->cuid,
            'community' => $this->c['community']->cuid,
            'space' => $this->open->cuid,
            'title' => 'Weekly review office hour',
            'starts_at' => '2026-11-18T16:00:00+00:00',
            'ends_at' => '2026-11-18T17:00:00+00:00',
            'time_zone' => 'Europe/Brussels',
            'state' => 'published',
        ])
        ->and($events[0])->not->toHaveKey('meeting_url')
        ->and($readme)->toContain('events.json: the events that you host');
});

test('the host, the owner, the admins and the moderators of the space delete an event; the others cannot', function () {
    $event = EventModel::factory()->inSpace($this->events)->hostedBy($this->c['admin'])->create();
    $hosted = EventModel::factory()->inSpace($this->open)->hostedBy($this->c['member'])->create();

    expect(fn () => app(DeletesEvents::class)->delete($this->c['member'], $event))->toThrow(AuthorizationException::class);

    app(DeletesEvents::class)->delete($this->c['moderator'], $event);
    app(DeletesEvents::class)->delete($this->c['member'], $hosted);

    expect(EventModel::query()->whereKey([$event->id, $hosted->id])->exists())->toBeFalse();
});

test('a delete takes the upload usages and the notification rows of the event by cascade', function () {
    $cover = evtImage($this->c['owner'], $this->c['community']);
    $event = evtCreate($this->c['owner'], $this->events, ['cover_upload_cuid' => $cover->cuid]);
    Notification::factory()->create(['user_id' => $this->c['member']->id, 'community_id' => $this->c['community']->id, 'space_id' => $this->events->id, 'kind' => 'event_new', 'actor_id' => $this->c['owner']->id, 'comment_id' => null, 'event_id' => $event->id, 'dedupe_key' => 'event_new:event:'.$event->id]);

    app(DeletesEvents::class)->delete($this->c['owner'], $event);

    expect($cover->usages()->count())->toBe(0)
        ->and(Notification::query()->where('event_id', $event->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------------------------------------------------
// Uploads (rule 42)
// ---------------------------------------------------------------------------------------------------------------------

test('an event has one usage row per image of its description and one for its cover, after each write', function () {
    $cover = evtImage($this->c['owner'], $this->c['community']);
    $inBody = evtImage($this->c['owner'], $this->c['community']);
    $other = evtImage($this->c['owner'], $this->c['community']);

    $event = evtCreate($this->c['owner'], $this->events, ['body' => "![A photo]({$inBody->url()})", 'cover_upload_cuid' => $cover->cuid]);
    expect($event->uploadUsages()->pluck('upload_id')->sort()->values()->all())->toBe(collect([$cover->id, $inBody->id])->sort()->values()->all());

    // The cover also in the description: one row
    app(UpdatesEvents::class)->update($this->c['owner'], $event, ['body' => "![A photo]({$cover->url()}) ![B]({$other->url()})"]);
    expect($event->uploadUsages()->pluck('upload_id')->sort()->values()->all())->toBe(collect([$cover->id, $other->id])->sort()->values()->all());

    app(UpdatesEvents::class)->update($this->c['owner'], $event, ['cover_upload_cuid' => null, 'body' => 'No image']);
    expect($event->uploadUsages()->count())->toBe(0);
});

test('uploads:prune keeps the cover and the images of an event, and deletes them after the event is gone', function () {
    $cover = evtImage($this->c['owner'], $this->c['community']);
    $inBody = evtImage($this->c['owner'], $this->c['community']);
    $event = evtCreate($this->c['owner'], $this->events, ['body' => "![A photo]({$inBody->url()})", 'cover_upload_cuid' => $cover->cuid]);
    Upload::query()->whereKey([$cover->id, $inBody->id])->update(['created_at' => now()->subDays(30)]);

    $this->artisan('uploads:prune')->assertSuccessful();
    expect(Upload::query()->whereKey([$cover->id, $inBody->id])->count())->toBe(2);

    app(DeletesEvents::class)->delete($this->c['owner'], $event);
    $this->artisan('uploads:prune')->assertSuccessful();
    expect(Upload::query()->whereKey([$cover->id, $inBody->id])->count())->toBe(0);
});

// ---------------------------------------------------------------------------------------------------------------------
// The host who leaves, the deleted account, the deleted content (rule 44)
// ---------------------------------------------------------------------------------------------------------------------

test('a host who leaves keeps host_id and loses every right of a host', function () {
    $event = evtCreate($this->c['member'], $this->open);
    $draft = evtCreate($this->c['member'], $this->open, ['title' => 'A draft']);

    $this->c['community']->removeUser($this->c['member']);
    $former = User::findOrFail($this->c['member']->id);

    expect($event->refresh()->host_id)->toBe($former->id)
        ->and(Gate::forUser($former)->allows('update', $event))->toBeFalse()
        ->and(Gate::forUser($former)->allows('delete', $event))->toBeFalse()
        ->and(Gate::forUser($former)->allows('view', $draft))->toBeFalse();
});

test('a deleted account leaves the hosted events without a host ("Deleted member")', function () {
    $event = evtCreate($this->c['member'], $this->open);

    app(DeletesAccounts::class)->delete($this->c['member']);

    expect($event->refresh()->exists)->toBeTrue()->and($event->host_id)->toBeNull()
        ->and($event->load('host')->toResource()->resolve()['host'])->toBeNull();
});

test('the option that deletes the content of a member does not delete the events they host', function () {
    $event = evtCreate($this->c['member'], $this->open);

    app(DeletesMemberContent::class)->delete($this->c['member']);

    expect(EventModel::query()->whereKey($event->id)->exists())->toBeTrue()
        ->and($event->refresh()->host_id)->toBe($this->c['member']->id);
});

// ---------------------------------------------------------------------------------------------------------------------
// Live updates (rule 58)
// ---------------------------------------------------------------------------------------------------------------------

test('a published event sends its name on the space channel with identifiers only; a draft sends nothing', function () {
    Event::fake([SpaceContentChanged::class]);
    $event = evtCreate($this->c['moderator'], $this->events, ['meeting_url' => 'https://meet.example.com/secret', 'place' => 'Secret room']);
    // A draft: nothing on the space channel, whatever happens to it
    app(UpdatesEvents::class)->update($this->c['moderator'], $event, ['title' => 'Changed']);
    $draftCopy = app(DuplicatesEvents::class)->duplicate($this->c['moderator'], $event);
    app(DeletesEvents::class)->delete($this->c['moderator'], $draftCopy);
    Event::assertNotDispatched(SpaceContentChanged::class);

    app(SetsEventStates::class)->setState($this->c['moderator'], $event, ['state' => 'published']);
    app(UpdatesEvents::class)->update($this->c['moderator'], $event, ['title' => 'Changed again']);
    app(SetsEventStates::class)->setState($this->c['moderator'], $event, ['state' => 'cancelled']);
    app(SetsEventStates::class)->setState($this->c['moderator'], $event, ['state' => 'published']);
    app(SetsEventStates::class)->setState($this->c['moderator'], $event, ['state' => 'draft']);
    app(UpdatesEvents::class)->update($this->c['moderator'], $event, ['title' => 'A draft again']);
    app(SetsEventStates::class)->setState($this->c['moderator'], $event, ['state' => 'published']);
    app(DeletesEvents::class)->delete($this->c['moderator'], $event);

    $sent = [];
    Event::assertDispatched(SpaceContentChanged::class, function (SpaceContentChanged $broadcast) use (&$sent) {
        $sent[] = [$broadcast->broadcastAs(), $broadcast->broadcastWith()];

        return true;
    });

    expect(array_column($sent, 0))->toBe([
        'space.event.created', 'space.event.updated', 'space.event.updated', 'space.event.updated',
        // Back to draft: the readers drop it; published again: it comes back
        'space.event.deleted', 'space.event.created', 'space.event.deleted',
    ]);
    foreach ($sent as [, $payload]) {
        expect(array_keys($payload))->toBe(['space_cuid', 'event_cuid', 'author_cuid'])
            ->and($payload['space_cuid'])->toBe($this->events->cuid)
            ->and($payload['event_cuid'])->toBe($event->cuid)
            ->and($payload['author_cuid'])->toBe($this->c['moderator']->cuid);
        $json = (string) json_encode($payload);
        expect($json)->not->toContain('Secret')->not->toContain('meet.example.com')->not->toContain('Changed');
    }
    expect(array_column(array_column($sent, 1), 'event_cuid'))->not->toContain($draftCopy->cuid);
    expect((new SpaceContentChanged('x', $this->c['community']->cuid, $this->events->cuid, []))->broadcastOn()[0]->name)
        ->toBe('private-community.'.$this->c['community']->cuid.'.space.'.$this->events->cuid);
});

test('a person who may not see an event gets the answer of an event that does not exist from every change, its state included', function () {
    $draft = evtCreate($this->c['admin'], $this->events);
    $calls = [
        fn (User $user) => app(UpdatesEvents::class)->update($user, $draft, ['title' => 'Seen']),
        fn (User $user) => app(SetsEventStates::class)->setState($user, $draft, ['state' => 'cancelled']),
        fn (User $user) => app(SetsEventStates::class)->setState($user, $draft, ['state' => 'published']),
        fn (User $user) => app(DuplicatesEvents::class)->duplicate($user, $draft),
        fn (User $user) => app(DeletesEvents::class)->delete($user, $draft),
    ];

    foreach (['moderator', 'member', 'outsider'] as $role) {
        foreach ($calls as $call) {
            expect(fn () => $call($this->c[$role]))->toThrow(ModelNotFoundException::class);
        }
    }
    expect(EventModel::query()->whereKey($draft->id)->exists())->toBeTrue()->and($draft->refresh()->state)->toBe(KnowiiEventState::Draft);

    // An event of a members space and an outsider
    $published = evtCreate($this->c['admin'], $this->events, ['state' => 'published']);
    expect(fn () => app(SetsEventStates::class)->setState($this->c['outsider'], $published, ['state' => 'cancelled']))->toThrow(ModelNotFoundException::class);
});

test('a moderator of the space cancels and deletes only the events that they can see, never a draft of another', function () {
    $draft = evtCreate($this->c['admin'], $this->events);

    expect(Gate::forUser($this->c['moderator'])->allows('cancel', $draft))->toBeFalse()
        ->and(Gate::forUser($this->c['moderator'])->allows('delete', $draft))->toBeFalse();
});
