<?php

use App\Actions\Events\AnswerEvent;
use App\Contracts\Events\AnswersEvents;
use App\Contracts\Events\CreatesEvents;
use App\Contracts\Events\DeletesEvents;
use App\Contracts\Events\DuplicatesEvents;
use App\Contracts\Events\SetsEventStates;
use App\Contracts\Events\UpdatesEvents;
use App\Contracts\Users\DeletesAccounts;
use App\Contracts\Users\ExportsUsers;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiEventAnswer;
use App\Enums\KnowiiEventLocationVisibility;
use App\Enums\KnowiiEventState;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceContentChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\BusinessException;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\Event as EventModel;
use App\Models\EventAnswer;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\EventAnswers;
use App\Support\NotificationAccess;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/*
 * The answers of members to events and the limit of places, slice E2a (#1515, ADR 0053, "The answers and the places"):
 * the action, the lock, the live update, and what happens to the answers when the event, the member or the account changes.
 */

beforeEach(function () {
    // A fixed now: 10 November 2026, 09:00 UTC. The events of the tests are a week later
    $this->travelTo(CarbonImmutable::parse('2026-11-10T09:00:00Z'));
    Event::fake([SpaceTreeChanged::class, SpaceContentChanged::class]);
    $this->c = communityWithMembers();
    foreach (['host', 'second', 'third'] as $key) {
        $this->c[$key] = User::factory()->withUserProfile()->create();
        $this->c['community']->users()->attach($this->c[$key], ['role' => KnowiiCommunityMemberRole::Member]);
    }
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->space = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'posting_policy' => KnowiiSpacePostingPolicy::Members]);
    $this->event = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create(['replay_expected' => true]);
});

function eanAnswer(string|User $member, EventModel $event, string $answer): EventAnswer
{
    return app(AnswersEvents::class)->answer($member instanceof User ? $member : test()->c[$member], $event, ['answer' => $answer]);
}

function eanClear(string|User $member, EventModel $event): void
{
    app(AnswersEvents::class)->clear($member instanceof User ? $member : test()->c[$member], $event);
}

/**
 * The answers of an event, by the key of the member in the community of the test
 *
 * @return array<string, string>
 */
function eanAnswers(EventModel $event): array
{
    $byUser = EventAnswer::query()->where('event_id', $event->id)->get()->mapWithKeys(fn (EventAnswer $answer) => [$answer->user_id => $answer->answer?->value]);
    $answers = [];
    foreach (test()->c as $key => $user) {
        if ($user instanceof User && $byUser->has($user->id)) {
            $answers[$key] = $byUser->get($user->id);
        }
    }

    return $answers;
}

/**
 * A "groups" events space with its access group "Buyers", which holds the given members of the community of the test,
 * and an event of the host in it
 *
 * @param  list<string>  $buyers
 * @param  array<string, mixed>  $attributes
 * @return array{0: EventModel, 1: AccessGroup, 2: Space}
 */
function eanGroupsEvent(array $buyers, array $attributes = []): array
{
    $test = test();
    $space = Space::factory()->inGroup($test->group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Groups, 'posting_policy' => KnowiiSpacePostingPolicy::Members]);
    $group = AccessGroup::query()->create(['community_id' => $test->c['community']->id, 'name' => 'Buyers']);
    $space->accessGroups()->attach($group);
    $group->members()->attach(array_map(static fn (string $key): int => $test->c[$key]->id, $buyers));

    return [EventModel::factory()->inSpace($space)->hostedBy($test->c['host'])->create($attributes), $group, $space];
}

/**
 * The member as a new request loads them: the instance of the test keeps the communities and the access groups that an
 * earlier call loaded
 */
function eanFresh(string $key): User
{
    return User::query()->findOrFail(test()->c[$key]->id);
}

/**
 * The names of the live updates of the space that the callback sends, with their payloads
 *
 * @return list<array{0: string, 1: array<string, string|null>}>
 */
function eanBroadcasts(Closure $callback): array
{
    Event::fake([SpaceTreeChanged::class, SpaceContentChanged::class]);
    $callback();

    return Event::dispatched(SpaceContentChanged::class)->map(fn (array $arguments) => [$arguments[0]->broadcastAs(), $arguments[0]->broadcastWith()])->all();
}

// ---------------------------------------------------------------------------------------------------------------------
// The answer
// ---------------------------------------------------------------------------------------------------------------------

test('a member answers, changes the answer and clears it: one row per member and event, and a "No" is a row', function () {
    $first = eanAnswer('member', $this->event, 'yes');
    expect($first->answer)->toBe(KnowiiEventAnswer::Yes)
        ->and($first->cuid)->toBeString()->not->toBe('')
        ->and($first->community_id)->toBe($this->c['community']->id)
        ->and($first->user_id)->toBe($this->c['member']->id);

    $this->travel(10)->minutes();
    $changed = eanAnswer('member', $this->event, 'no');
    expect($changed->id)->toBe($first->id)
        ->and($changed->cuid)->toBe($first->cuid)
        ->and($changed->answer)->toBe(KnowiiEventAnswer::No)
        ->and($changed->created_at?->toIso8601String())->toBe('2026-11-10T09:00:00+00:00')
        ->and($changed->updated_at?->toIso8601String())->toBe('2026-11-10T09:10:00+00:00')
        ->and(eanAnswers($this->event))->toBe(['member' => 'no']);

    eanAnswer('second', $this->event, 'replay');
    expect(eanAnswers($this->event))->toBe(['member' => 'no', 'second' => 'replay']);

    eanClear('member', $this->event);
    expect(eanAnswers($this->event))->toBe(['second' => 'replay']);
    // A clear without an answer changes nothing
    eanClear('member', $this->event);
    eanClear('third', $this->event);
    expect(eanAnswers($this->event))->toBe(['second' => 'replay']);
});

test('the same answer again changes nothing, not even its date', function () {
    $first = eanAnswer('member', $this->event, 'yes');
    $this->travel(10)->minutes();
    $again = eanAnswer('member', $this->event, 'yes');

    expect($again->id)->toBe($first->id)->and($again->updated_at?->toIso8601String())->toBe('2026-11-10T09:00:00+00:00');
});

test('"Replay only" needs an event whose host says that a replay will exist; the rows stay when the host says no more', function () {
    eanAnswer('member', $this->event, 'replay');

    app(UpdatesEvents::class)->update($this->c['host'], $this->event, ['replay_expected' => false]);
    expect($this->event->fresh()->replay_expected)->toBeFalse()
        ->and(eanAnswers($this->event))->toBe(['member' => 'replay']);

    // No new "Replay only"; the member who has one chooses Yes or No when they want
    expect(fn () => eanAnswer('second', $this->event->fresh(), 'replay'))->toThrow(ValidationException::class, AnswerEvent::NO_REPLAY_MESSAGE);
    expect(eanAnswer('member', $this->event->fresh(), 'yes')->answer)->toBe(KnowiiEventAnswer::Yes);
});

test('the answer is one of the three: anything else is a field error', function (mixed $input) {
    expect(fn () => app(AnswersEvents::class)->answer($this->c['member'], $this->event, $input))->toThrow(ValidationException::class);
    expect(eanAnswers($this->event))->toBe([]);
})->with([
    'nothing' => [[]],
    'maybe' => [['answer' => 'maybe']],
    'an empty text' => [['answer' => '']],
    'a list' => [['answer' => ['yes', 'no']]],
    'in capitals' => [['answer' => 'YES']],
]);

test('an event that started and did not end takes answers; an event that ended takes none', function () {
    $running = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create(['starts_at' => now()->subMinutes(30), 'ends_at' => now()->addMinutes(30)]);
    expect(eanAnswer('member', $running, 'yes')->answer)->toBe(KnowiiEventAnswer::Yes);

    // The end passes: no new answer, no change and no clear, and the answer stays
    $this->travel(31)->minutes();
    foreach ([fn () => eanAnswer('second', $running->fresh(), 'yes'), fn () => eanAnswer('member', $running->fresh(), 'no'), fn () => eanClear('member', $running->fresh())] as $call) {
        expect($call)->toThrow(BusinessException::class, AnswerEvent::OVER_MESSAGE);
    }
    expect(eanAnswers($running))->toBe(['member' => 'yes']);
});

test('a cancelled event takes no answer, no change and no clear; "Publish again" keeps the rows and takes answers again', function () {
    eanAnswer('member', $this->event, 'yes');
    eanAnswer('second', $this->event, 'no');
    app(SetsEventStates::class)->setState($this->c['host'], $this->event, ['state' => 'cancelled']);

    foreach ([fn () => eanAnswer('third', $this->event->fresh(), 'yes'), fn () => eanAnswer('member', $this->event->fresh(), 'no'), fn () => eanClear('member', $this->event->fresh())] as $call) {
        expect($call)->toThrow(BusinessException::class, AnswerEvent::CANCELLED_MESSAGE);
    }
    expect(eanAnswers($this->event))->toBe(['member' => 'yes', 'second' => 'no']);

    app(SetsEventStates::class)->setState($this->c['host'], $this->event, ['state' => 'published']);
    expect(eanAnswers($this->event))->toBe(['member' => 'yes', 'second' => 'no']);
    eanAnswer('third', $this->event->fresh(), 'yes');
    eanClear('second', $this->event->fresh());
    expect(eanAnswers($this->event))->toBe(['member' => 'yes', 'third' => 'yes']);
});

test('the refusals come in their order: not found, not a member, the state of the event, then the field', function () {
    $draft = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->draft()->create();
    $cancelled = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->cancelled()->create();
    $public = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => 'public']);
    $publicCancelled = EventModel::factory()->inSpace($public)->hostedBy($this->c['host'])->cancelled()->create();
    $wrong = ['answer' => 'maybe'];

    // A draft that the member cannot see, and an event of a space that an outsider cannot read: before anything else
    expect(fn () => app(AnswersEvents::class)->answer($this->c['member'], $draft, $wrong))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(AnswersEvents::class)->clear($this->c['member'], $draft))->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(AnswersEvents::class)->answer($this->c['outsider'], $cancelled, $wrong))->toThrow(ModelNotFoundException::class)
        // A person who is not a member, in a public space: before the state and the field
        ->and(fn () => app(AnswersEvents::class)->answer($this->c['outsider'], $publicCancelled, $wrong))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AnswersEvents::class)->clear($this->c['outsider'], $publicCancelled))->toThrow(AuthorizationException::class)
        // The state: before the field
        ->and(fn () => app(AnswersEvents::class)->answer($this->c['member'], $cancelled, $wrong))->toThrow(BusinessException::class, AnswerEvent::CANCELLED_MESSAGE)
        ->and(fn () => app(AnswersEvents::class)->answer($this->c['host'], $draft, $wrong))->toThrow(BusinessException::class, AnswerEvent::DRAFT_MESSAGE)
        // The field
        ->and(fn () => app(AnswersEvents::class)->answer($this->c['member'], $this->event, $wrong))->toThrow(ValidationException::class);
    expect(EventAnswer::query()->count())->toBe(0);
});

test('a member who cannot read the space of the event does not answer it', function () {
    $this->space->update(['visibility' => 'groups']);

    expect(fn () => eanAnswer('member', $this->event->fresh(), 'yes'))->toThrow(ModelNotFoundException::class)
        ->and(Gate::forUser($this->c['member'])->allows('answer', $this->event->fresh()))->toBeFalse()
        ->and(Gate::forUser($this->c['owner'])->allows('answer', $this->event->fresh()))->toBeTrue();
});

test('the log lines of an answer hold the cuid of the event, never the answer or the member', function () {
    $lines = [];
    Log::listen(function (MessageLogged $logged) use (&$lines) {
        $lines[] = $logged->message.' '.json_encode($logged->context);
    });

    eanAnswer('member', $this->event, 'replay');
    eanClear('member', $this->event);

    $log = implode("\n", $lines);
    expect($lines)->not->toBeEmpty()
        ->and($log)->toContain($this->event->cuid)
        ->not->toContain('replay')
        ->not->toContain($this->c['member']->cuid)
        ->not->toContain($this->c['member']->name)
        ->not->toContain($this->c['member']->username)
        ->not->toContain($this->c['member']->email);
});

// ---------------------------------------------------------------------------------------------------------------------
// The limit of places
// ---------------------------------------------------------------------------------------------------------------------

test('the limit counts the Yes answers only: the last place is taken, and the next Yes is refused', function () {
    $this->event->update(['capacity' => 2]);
    eanAnswer('member', $this->event, 'yes');
    eanAnswer('admin', $this->event, 'no');
    eanAnswer('moderator', $this->event, 'replay');
    expect($this->event->fresh()->placesLeft())->toBe(1);

    // The last place
    eanAnswer('second', $this->event, 'yes');
    expect($this->event->fresh()->placesLeft())->toBe(0);

    // A new Yes, and a change from another answer to Yes
    expect(fn () => eanAnswer('third', $this->event, 'yes'))->toThrow(BusinessException::class, 'This event is full.')
        ->and(fn () => eanAnswer('moderator', $this->event, 'yes'))->toThrow(BusinessException::class, 'This event is full.')
        ->and(fn () => eanAnswer('admin', $this->event, 'yes'))->toThrow(BusinessException::class, 'This event is full.');
    // The other answers are never refused, and the same Yes again is accepted
    eanAnswer('third', $this->event, 'no');
    expect(eanAnswer('second', $this->event, 'yes')->answer)->toBe(KnowiiEventAnswer::Yes)
        ->and(eanAnswers($this->event))->toBe(['admin' => 'no', 'moderator' => 'replay', 'member' => 'yes', 'second' => 'yes', 'third' => 'no']);
});

test('a member who leaves a Yes frees a place, and nobody is promoted', function () {
    $this->event->update(['capacity' => 1]);
    eanAnswer('member', $this->event, 'yes');
    eanAnswer('second', $this->event, 'replay');
    expect(fn () => eanAnswer('third', $this->event, 'yes'))->toThrow(BusinessException::class, 'This event is full.');

    // From Yes to No: the place is free, and the member who wanted it still has no answer
    eanAnswer('member', $this->event, 'no');
    expect(eanAnswers($this->event))->toBe(['member' => 'no', 'second' => 'replay'])
        ->and($this->event->fresh()->placesLeft())->toBe(1);
    eanAnswer('third', $this->event, 'yes');

    // A clear frees it too
    eanClear('third', $this->event);
    expect($this->event->fresh()->placesLeft())->toBe(1);
    eanAnswer('second', $this->event, 'yes');
    expect($this->event->fresh()->placesLeft())->toBe(0);
});

test('a host lowers the limit under the number of Yes answers: nobody is removed, no place is left, and no new Yes passes', function () {
    foreach (['member', 'second', 'third'] as $member) {
        eanAnswer($member, $this->event, 'yes');
    }

    $event = app(UpdatesEvents::class)->update($this->c['host'], $this->event, ['capacity' => 2]);
    expect($event->capacity)->toBe(2)
        ->and($event->fresh()->placesLeft())->toBe(0)
        ->and(eanAnswers($this->event))->toBe(['member' => 'yes', 'second' => 'yes', 'third' => 'yes'])
        ->and(fn () => eanAnswer('moderator', $this->event, 'yes'))->toThrow(BusinessException::class, 'This event is full.');
    // The same Yes again at the limit
    expect(eanAnswer('third', $this->event, 'yes')->answer)->toBe(KnowiiEventAnswer::Yes);

    // No limit again
    app(UpdatesEvents::class)->update($this->c['host'], $this->event, ['capacity' => null]);
    expect($this->event->fresh()->capacity)->toBeNull()->and($this->event->fresh()->placesLeft())->toBeNull();
    eanAnswer('moderator', $this->event, 'yes');
});

test('the Yes of a member who lost the access to the space takes no place, and takes it again when the access is back', function () {
    [$event, $buyers] = eanGroupsEvent(['host', 'member', 'second', 'third'], ['capacity' => 1]);
    eanAnswer('member', $event, 'yes');
    expect(fn () => eanAnswer('second', $event, 'yes'))->toThrow(BusinessException::class, 'This event is full.')
        ->and($event->fresh()->placesLeft())->toBe(0);

    // The member loses the access group and stays in the community: the row stays, and the place is free
    $buyers->members()->detach($this->c['member']->id);
    expect($event->fresh()->placesLeft())->toBe(1)
        ->and($event->fresh()->yesCount())->toBe(0);
    expect(eanAnswer(eanFresh('second'), $event, 'yes')->answer)->toBe(KnowiiEventAnswer::Yes);
    expect(eanAnswers($event))->toBe(['member' => 'yes', 'second' => 'yes'])
        ->and($event->fresh()->placesLeft())->toBe(0)
        ->and(fn () => eanAnswer(eanFresh('third'), $event, 'yes'))->toThrow(BusinessException::class, 'This event is full.');
    // The member without the access changes nothing: the event does not exist for them
    expect(fn () => eanAnswer(eanFresh('member'), $event, 'no'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => eanClear(eanFresh('member'), $event))->toThrow(ModelNotFoundException::class);

    // The access comes back: the answer counts again. The event is over its limit: nobody is removed, no place is left
    $buyers->members()->attach($this->c['member']->id);
    expect(eanAnswers($event))->toBe(['member' => 'yes', 'second' => 'yes'])
        ->and($event->fresh()->yesCount())->toBe(2)
        ->and($event->fresh()->placesLeft())->toBe(0)
        ->and(fn () => eanAnswer(eanFresh('third'), $event, 'yes'))->toThrow(BusinessException::class, 'This event is full.');
    // One Yes goes: still at the limit. The second one goes: one place
    eanClear(eanFresh('second'), $event);
    expect($event->fresh()->placesLeft())->toBe(0);
    eanAnswer(eanFresh('member'), $event, 'no');
    expect($event->fresh()->placesLeft())->toBe(1);
});

test('a list and a single read count the places in the same way', function () {
    [$first, $buyers, $space] = eanGroupsEvent(['host', 'member', 'second', 'third'], ['capacity' => 5]);
    $second = EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])->create(['capacity' => 2]);
    $open = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create(['capacity' => 3]);
    foreach (['member', 'second', 'third'] as $key) {
        EventAnswer::factory()->to($first)->of($this->c[$key])->yes()->create();
        EventAnswer::factory()->to($open)->of($this->c[$key])->yes()->create();
    }
    EventAnswer::factory()->to($second)->of($this->c['member'])->yes()->create();
    EventAnswer::factory()->to($second)->of($this->c['second'])->no()->create();
    $buyers->members()->detach([$this->c['member']->id, $this->c['third']->id]);

    $events = EventModel::query()->whereKey([$first->id, $second->id, $open->id])->orderBy('id')->get();
    EventAnswers::preload($events, eanFresh('host'));

    // In the "groups" space only the Yes of the member who still has the group takes a place; in the other space all do
    expect($events->map(fn (EventModel $event) => $event->placesLeft())->all())->toBe([4, 2, 0])
        ->and([$first->fresh()->placesLeft(), $second->fresh()->placesLeft(), $open->fresh()->placesLeft()])->toBe([4, 2, 0]);
});

test('the database and the policy of the space agree on the members whose answer counts', function (string $visibility, string $groupOn, array $expected) {
    $space = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => $visibility]);
    $buyers = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Buyers']);
    $others = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Others']);
    match ($groupOn) {
        'the space' => $space->accessGroups()->attach($buyers),
        'the space group' => $this->group->accessGroups()->attach($buyers),
        default => null,
    };
    // A group that opens the space, a group that opens nothing, and a person who is not a member with a row in the first
    $buyers->members()->attach([$this->c['second']->id, $this->c['moderator']->id, $this->c['outsider']->id]);
    $others->members()->attach([$this->c['third']->id]);
    $event = EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])->create();
    $people = array_filter($this->c, static fn (mixed $value): bool => $value instanceof User);
    foreach ($people as $user) {
        EventAnswer::factory()->to($event)->of($user)->yes()->create();
    }

    $counted = EventAnswer::query()->ofReaders()->where('event_id', $event->id)->pluck('user_id')->all();
    $byDatabase = array_keys(array_filter($people, static fn (User $user): bool => in_array($user->id, $counted, true)));
    $byPolicy = array_keys(array_filter($people, fn (User $user): bool => NotificationAccess::canView(User::query()->findOrFail($user->id), $this->c['community']->fresh(), $space->fresh())));

    expect($byDatabase)->toBe($byPolicy)->toBe($expected)
        ->and($event->fresh()->yesCount())->toBe(count($expected));
})->with([
    'a public space' => ['public', 'nothing', ['owner', 'admin', 'moderator', 'member', 'host', 'second', 'third']],
    'a members space' => ['members', 'nothing', ['owner', 'admin', 'moderator', 'member', 'host', 'second', 'third']],
    'a groups space, the group on the space' => ['groups', 'the space', ['owner', 'admin', 'moderator', 'second']],
    'a groups space, the group on the space group' => ['groups', 'the space group', ['owner', 'admin', 'moderator', 'second']],
    'a groups space that no group opens' => ['groups', 'nothing', ['owner', 'admin']],
]);

test('the limit is 1 to 5 000 or empty, on a create and on a change', function () {
    $input = fn (mixed $capacity) => ['title' => 'With a limit', 'starts_at' => '2026-11-18T17:00:00+01:00', 'ends_at' => '2026-11-18T18:00:00+01:00', 'time_zone' => 'Europe/Brussels', 'capacity' => $capacity];

    foreach ([0, -1, 5001, 'many', 2.5] as $wrong) {
        expect(fn () => app(CreatesEvents::class)->create($this->c['host'], $this->space, $input($wrong)))->toThrow(ValidationException::class)
            ->and(fn () => app(UpdatesEvents::class)->update($this->c['host'], $this->event, ['capacity' => $wrong]))->toThrow(ValidationException::class);
    }
    expect(app(CreatesEvents::class)->create($this->c['host'], $this->space, $input(1))->capacity)->toBe(1)
        ->and(app(CreatesEvents::class)->create($this->c['host'], $this->space, $input(5000))->capacity)->toBe(5000)
        ->and(app(CreatesEvents::class)->create($this->c['host'], $this->space, $input(null))->capacity)->toBeNull();

    // Without the fields: no limit and no replay
    $plain = app(CreatesEvents::class)->create($this->c['host'], $this->space, array_diff_key($input(null), ['capacity' => true]));
    expect($plain->capacity)->toBeNull()->and($plain->replay_expected)->toBeFalse()
        ->and(app(CreatesEvents::class)->create($this->c['host'], $this->space, [...$input(40), 'replay_expected' => true])->replay_expected)->toBeTrue()
        ->and(fn () => app(CreatesEvents::class)->create($this->c['host'], $this->space, [...$input(40), 'replay_expected' => 'perhaps']))->toThrow(ValidationException::class);
    // The database holds the same limit
    expect(fn () => DB::transaction(fn () => DB::table('events')->where('id', $this->event->id)->update(['capacity' => 5001])))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------------------------------------------------
// The lock and the unique index (rule 13)
// ---------------------------------------------------------------------------------------------------------------------

test('an answer, a clear, a change of the limit and a change of state lock the row of the event inside a transaction, before they read or count the answers', function () {
    $this->event->update(['capacity' => 5]);
    eanAnswer('second', $this->event, 'no');
    $outside = DB::transactionLevel();
    // What each call does, in its order: "lock" (the row of the event, with the level of its transaction), then every
    // statement on the answers ("count" for a count and for "does one exist", "read" and "write" for the others)
    $steps = [];
    DB::listen(function (QueryExecuted $query) use (&$steps) {
        $sql = strtolower($query->sql);
        if (preg_match('/from "events" where .* for update$/', $sql) === 1) {
            $steps[] = 'lock at level '.DB::transactionLevel();
        } elseif (str_contains($sql, '"event_answers"')) {
            $steps[] = match (true) {
                str_starts_with($sql, 'select count(*)'), str_starts_with($sql, 'select exists(') => 'count',
                str_starts_with($sql, 'select') => 'read',
                default => 'write',
            };
        }
    });
    $stepsOf = function (Closure $call) use (&$steps): array {
        $steps = [];
        rescue($call, null, false);

        return $steps;
    };
    $lock = 'lock at level '.($outside + 1);

    // A new Yes on an event with a limit: the lock, the answer of the member, the count, then the write
    expect($stepsOf(fn () => eanAnswer('member', $this->event, 'yes')))->toBe([$lock, 'read', 'count', 'write', 'read'])
        // A clear: the lock, then the row and its delete
        ->and($stepsOf(fn () => eanClear('member', $this->event)))->toBe([$lock, 'read', 'write'])
        // A change of the limit: the lock, and no answer is read
        ->and($stepsOf(fn () => app(UpdatesEvents::class)->update($this->c['host'], $this->event, ['capacity' => 3])))->toBe([$lock])
        // A return to draft: the lock, then "did a member answer": refused here, a "No" is an answer
        ->and($stepsOf(fn () => app(SetsEventStates::class)->setState($this->c['host'], $this->event, ['state' => 'draft'])))->toBe([$lock, 'count'])
        ->and($this->event->fresh()->state)->toBe(KnowiiEventState::Published)
        ->and($stepsOf(fn () => app(SetsEventStates::class)->setState($this->c['host'], $this->event, ['state' => 'cancelled'])))->toBe([$lock])
        ->and($stepsOf(fn () => app(SetsEventStates::class)->setState($this->c['host'], $this->event, ['state' => 'published'])))->toBe([$lock])
        ->and($this->event->fresh()->state)->toBe(KnowiiEventState::Published);
});

test('an answer holds the row of the membership until the commit, after the lock of the event and before the write', function () {
    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements) {
        $statements[] = strtolower($query->sql);
    });

    eanAnswer('member', $this->event, 'yes');

    $first = fn (string $pattern): ?int => array_key_first(array_filter($statements, static fn (string $sql): bool => preg_match($pattern, $sql) === 1));
    $lock = $first('/from "events" where .* for update$/');
    $membership = $first('/from "community_members" where .* for share$/');
    $write = $first('/^insert into "event_answers"/');
    expect($lock)->not->toBeNull()->and($membership)->toBeGreaterThan($lock)->and($write)->toBeGreaterThan($membership);
});

test('inside the lock the action reads the member again: a leave and a lost access group that another request just wrote leave no row', function () {
    $public = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Public]);
    $inPublic = EventModel::factory()->inSpace($public)->hostedBy($this->c['host'])->create();
    [$inGroups, $buyers] = eanGroupsEvent(['host', 'second', 'third'], ['capacity' => 5]);
    EventAnswer::factory()->to($inGroups)->of($this->c['third'])->yes()->create();

    // The models of these requests still say "a member" and "in the group"
    $leaver = eanFresh('member');
    $second = eanFresh('second');
    $third = eanFresh('third');
    expect($leaver->belongsToCommunity($this->c['community']))->toBeTrue()
        ->and(Gate::forUser($second)->allows('answer', $inGroups))->toBeTrue()
        ->and(Gate::forUser($third)->allows('answer', $inGroups))->toBeTrue();
    $this->c['community']->removeUser(eanFresh('member'));
    $buyers->members()->detach([$this->c['second']->id, $this->c['third']->id]);

    // The member who left: no event of a members space, and no answer in a public space
    expect(fn () => eanAnswer($leaver, $this->event, 'yes'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => eanAnswer($leaver, $inPublic, 'yes'))->toThrow(AuthorizationException::class)
        ->and(fn () => eanClear($leaver, $inPublic))->toThrow(AuthorizationException::class)
        // The members who lost the group: the event does not exist for them, for a new answer, a change and a clear
        ->and(fn () => eanAnswer($second, $inGroups, 'yes'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => eanAnswer($third, $inGroups, 'no'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => eanClear($third, $inGroups))->toThrow(ModelNotFoundException::class);
    expect(EventAnswer::query()->where('user_id', $leaver->id)->exists())->toBeFalse()
        ->and(EventAnswer::query()->where('user_id', $second->id)->exists())->toBeFalse()
        // The row of the member who lost the group stays as it was
        ->and(eanAnswers($inGroups))->toBe(['third' => 'yes']);
});

test('inside the lock a change of state reads the event again: the state and the start that another request just wrote decide', function () {
    // The model of each request still says "published" and "in a week"
    $stale = fn (): EventModel => tap(EventModel::query()->findOrFail($this->event->id), function (EventModel $event): void {
        DB::table('events')->where('id', $this->event->id)->update(['state' => 'published', 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHour()]);
    });
    $write = fn (array $values) => DB::table('events')->where('id', $this->event->id)->update($values);
    $set = fn (string $member, EventModel $event, string $state) => app(SetsEventStates::class)->setState($this->c[$member], $event, ['state' => $state]);

    // Another request cancelled it: a cancelled event does not go back to draft
    $event = $stale();
    $write(['state' => 'cancelled']);
    expect(fn () => $set('host', $event, 'draft'))->toThrow(BusinessException::class, 'A cancelled event cannot go back to draft. Publish it again instead.')
        ->and($this->event->fresh()->state)->toBe(KnowiiEventState::Cancelled)
        ->and($this->event->fresh()->sequence)->toBe(0);
    // The same state again changes nothing, also when the model did not know it
    $event = $stale();
    $write(['state' => 'cancelled']);
    expect($set('host', $event, 'cancelled')->state)->toBe(KnowiiEventState::Cancelled)
        ->and($this->event->fresh()->sequence)->toBe(0);

    // Another request moved the start: an event that started does not go back to draft
    $event = $stale();
    $write(['starts_at' => now()->subMinutes(5)]);
    expect(fn () => $set('host', $event, 'draft'))->toThrow(BusinessException::class, 'An event that started cannot go back to draft. Cancel it instead.')
        ->and($this->event->fresh()->state)->toBe(KnowiiEventState::Published);

    // Another request made it a draft: a draft is not cancelled, and a moderator does not see a draft of another host
    $event = $stale();
    $write(['state' => 'draft']);
    expect(fn () => $set('host', $event, 'cancelled'))->toThrow(BusinessException::class, 'A draft cannot be cancelled. Delete it instead.');
    $event = $stale();
    $write(['state' => 'draft']);
    expect(fn () => $set('moderator', $event, 'cancelled'))->toThrow(ModelNotFoundException::class)
        ->and($this->event->fresh()->state)->toBe(KnowiiEventState::Draft)
        ->and($this->event->fresh()->sequence)->toBe(0);
});

test('inside the lock the action reads the event again: a cancel and a lower limit that another request just wrote are seen', function () {
    // The model of this request still says "published" and "no limit"
    $stale = EventModel::query()->findOrFail($this->event->id);
    eanAnswer('second', $this->event, 'yes');
    DB::table('events')->where('id', $this->event->id)->update(['capacity' => 1]);
    expect(fn () => eanAnswer('member', $stale, 'yes'))->toThrow(BusinessException::class, 'This event is full.');

    DB::table('events')->where('id', $this->event->id)->update(['state' => 'cancelled']);
    expect(fn () => eanAnswer('member', $stale, 'no'))->toThrow(BusinessException::class, AnswerEvent::CANCELLED_MESSAGE)
        ->and(fn () => eanClear('second', $stale))->toThrow(BusinessException::class, AnswerEvent::CANCELLED_MESSAGE);
    expect(eanAnswers($this->event))->toBe(['second' => 'yes']);
});

test('the database refuses a second row for the same member and event', function () {
    eanAnswer('member', $this->event, 'yes');
    $row = ['event_id' => $this->event->id, 'user_id' => $this->c['member']->id, 'community_id' => $this->c['community']->id, 'answer' => 'no', 'cuid' => 'a-second-row'];

    // A savepoint: the refused statement must not end the transaction of the test
    expect(fn () => DB::transaction(fn () => DB::table('event_answers')->insert($row)))->toThrow(UniqueConstraintViolationException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('event_answers')->insert([...$row, 'user_id' => $this->c['second']->id, 'answer' => 'maybe'])))->toThrow(QueryException::class);
    expect(eanAnswers($this->event))->toBe(['member' => 'yes']);
});

// ---------------------------------------------------------------------------------------------------------------------
// The live update (rules 27 to 29)
// ---------------------------------------------------------------------------------------------------------------------

test('the places of an event with a limit change: one live update with the event only, never the member', function () {
    $this->event->update(['capacity' => 5]);

    $sent = eanBroadcasts(fn () => eanAnswer('member', $this->event, 'yes'));
    expect($sent)->toHaveCount(1)
        ->and($sent[0][0])->toBe('space.event.answers.changed')
        ->and($sent[0][1])->toBe(['space_cuid' => $this->space->cuid, 'event_cuid' => $this->event->cuid]);
    $broadcast = Event::dispatched(SpaceContentChanged::class)->first()[0];
    // The payload has exactly the event: no author, no answer, no count
    expect($broadcast->payload)->toBe(['event_cuid' => $this->event->cuid])
        ->and($broadcast->broadcastOn()[0]->name)->toBe('private-community.'.$this->c['community']->cuid.'.space.'.$this->space->cuid)
        ->and((string) json_encode([$broadcast->payload, $broadcast->broadcastWith()]))->not->toContain($this->c['member']->cuid);

    // Yes to No, No to Yes and a clear of a Yes change the places
    expect(array_column(eanBroadcasts(fn () => eanAnswer('member', $this->event, 'no')), 0))->toBe(['space.event.answers.changed'])
        ->and(array_column(eanBroadcasts(fn () => eanAnswer('member', $this->event, 'yes')), 0))->toBe(['space.event.answers.changed'])
        ->and(array_column(eanBroadcasts(fn () => eanClear('member', $this->event)), 0))->toBe(['space.event.answers.changed']);
});

test('no live update for an answer that does not change the places, and none without a limit', function () {
    $this->event->update(['capacity' => 5]);
    eanAnswer('second', $this->event, 'yes');

    // With a limit: a No, a Replay only, a change between them, their clear, the same Yes again, a clear without a row
    $quiet = [
        fn () => eanAnswer('member', $this->event, 'no'),
        fn () => eanAnswer('member', $this->event, 'replay'),
        fn () => eanClear('member', $this->event),
        fn () => eanAnswer('second', $this->event, 'yes'),
        fn () => eanClear('third', $this->event),
    ];
    foreach ($quiet as $index => $call) {
        expect(eanBroadcasts($call))->toBe([], "call {$index}");
    }
    // A refused Yes sends nothing
    $this->event->update(['capacity' => 1]);
    expect(eanBroadcasts(fn () => rescue(fn () => eanAnswer('third', $this->event, 'yes'), null, false)))->toBe([]);

    // Without a limit: nothing, whatever the answer
    $this->event->update(['capacity' => null]);
    foreach ([fn () => eanAnswer('member', $this->event, 'yes'), fn () => eanAnswer('member', $this->event, 'no'), fn () => eanAnswer('third', $this->event, 'yes'), fn () => eanClear('third', $this->event)] as $index => $call) {
        expect(eanBroadcasts($call))->toBe([], "without a limit, call {$index}");
    }
});

test('a change of the limit, of the replay or of who sees the address is an update of the event on the space channel', function (array $change) {
    $sent = eanBroadcasts(fn () => app(UpdatesEvents::class)->update($this->c['host'], $this->event, $change));

    expect(array_column($sent, 0))->toBe(['space.event.updated'])
        ->and($sent[0][1]['event_cuid'])->toBe($this->event->cuid);
    // None of them is a change of the time, the address or the place
    expect($this->event->fresh()->sequence)->toBe(0);
})->with([
    'the limit' => [['capacity' => 12]],
    'the replay' => [['replay_expected' => false]],
    'who sees the address' => [['location_visibility' => 'going']],
]);

// ---------------------------------------------------------------------------------------------------------------------
// The event changes
// ---------------------------------------------------------------------------------------------------------------------

test('a published event with an answer does not go back to draft, also when the answer is a No', function (string $answer) {
    eanAnswer('member', $this->event, $answer);

    expect(fn () => app(SetsEventStates::class)->setState($this->c['host'], $this->event, ['state' => 'draft']))
        ->toThrow(BusinessException::class, 'Members answered this event. Cancel it instead.');
    expect($this->event->fresh()->state)->toBe(KnowiiEventState::Published)->and($this->event->fresh()->sequence)->toBe(0);

    // Without an answer, it goes back
    eanClear('member', $this->event);
    expect(app(SetsEventStates::class)->setState($this->c['host'], $this->event, ['state' => 'draft'])->state)->toBe(KnowiiEventState::Draft);
})->with(['yes', 'replay', 'no']);

test('an event goes back to draft when no member who can read the space has an answer; the other rows stay and count again later', function () {
    [$event, $buyers] = eanGroupsEvent(['host', 'member', 'second'], ['capacity' => 1]);
    eanAnswer('member', $event, 'yes');
    eanAnswer('second', $event, 'no');
    $toDraft = fn () => app(SetsEventStates::class)->setState($this->c['host'], $event->fresh(), ['state' => 'draft']);
    expect($toDraft)->toThrow(BusinessException::class, 'Members answered this event. Cancel it instead.');

    // One of the two loses the access: the other one still has an answer
    $buyers->members()->detach($this->c['member']->id);
    expect($toDraft)->toThrow(BusinessException::class, 'Members answered this event. Cancel it instead.');
    $buyers->members()->detach($this->c['second']->id);
    expect($toDraft()->state)->toBe(KnowiiEventState::Draft)
        ->and(eanAnswers($event))->toBe(['member' => 'yes', 'second' => 'no']);

    // Published again, and the access is back: the answers count as before
    app(SetsEventStates::class)->setState($this->c['host'], $event->fresh(), ['state' => 'published']);
    expect($event->fresh()->placesLeft())->toBe(1);
    $buyers->members()->attach([$this->c['member']->id, $this->c['second']->id]);
    expect($event->fresh()->placesLeft())->toBe(0)
        ->and($toDraft)->toThrow(BusinessException::class, 'Members answered this event. Cancel it instead.');
});

test('a copy keeps the limit and the replay choice, and has no answers', function () {
    $this->event->update(['capacity' => 40, 'location_visibility' => KnowiiEventLocationVisibility::Going]);
    eanAnswer('member', $this->event, 'yes');
    eanAnswer('second', $this->event, 'replay');

    $copy = app(DuplicatesEvents::class)->duplicate($this->c['host'], $this->event->fresh());

    expect($copy->capacity)->toBe(40)
        ->and($copy->replay_expected)->toBeTrue()
        ->and($copy->fresh()->capacity)->toBe(40)
        ->and($copy->fresh()->replay_expected)->toBeTrue()
        ->and(EventAnswer::query()->where('event_id', $copy->id)->exists())->toBeFalse()
        ->and(eanAnswers($this->event))->toBe(['member' => 'yes', 'second' => 'replay']);
});

test('a deleted event deletes its answers', function () {
    eanAnswer('member', $this->event, 'yes');
    $other = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create();
    eanAnswer('member', $other, 'no');

    app(DeletesEvents::class)->delete($this->c['host'], $this->event);

    expect(EventAnswer::query()->where('event_id', $this->event->id)->exists())->toBeFalse()
        ->and(eanAnswers($other))->toBe(['member' => 'no']);
});

// ---------------------------------------------------------------------------------------------------------------------
// The member changes
// ---------------------------------------------------------------------------------------------------------------------

test('a member who leaves or is removed loses their answers in that community only, and starts with none when they come back', function () {
    $elsewhere = Community::factory()->create();
    $elsewhere->users()->attach($this->c['member'], ['role' => KnowiiCommunityMemberRole::Member]);
    $otherGroup = SpaceGroup::factory()->create(['community_id' => $elsewhere->id]);
    $otherSpace = Space::factory()->inGroup($otherGroup)->create(['type' => KnowiiSpaceType::Events]);
    $otherEvent = EventModel::factory()->inSpace($otherSpace)->create();
    $member = User::findOrFail($this->c['member']->id);
    eanAnswer($member, $this->event, 'yes');
    eanAnswer($member, $otherEvent, 'yes');
    eanAnswer('second', $this->event, 'yes');

    $this->c['community']->removeUser($member);

    expect(eanAnswers($this->event))->toBe(['second' => 'yes'])
        ->and(EventAnswer::query()->where('event_id', $otherEvent->id)->where('user_id', $member->id)->exists())->toBeTrue();

    // Back in the community: no answer
    $this->c['community']->users()->attach($member, ['role' => KnowiiCommunityMemberRole::Member]);
    expect($this->event->fresh()->answerOf(User::findOrFail($member->id)))->toBeNull();
});

test('a deleted account deletes its answers; the event that it hosted stays', function () {
    eanAnswer('host', $this->event, 'yes');
    eanAnswer('member', $this->event, 'no');

    app(DeletesAccounts::class)->delete($this->c['host']);

    expect(eanAnswers($this->event))->toBe(['member' => 'no'])
        ->and(EventAnswer::query()->where('user_id', $this->c['host']->id)->exists())->toBeFalse()
        ->and($this->event->fresh()->host_id)->toBeNull();
});

test('the export of a member holds their answers: the event by its cuid and its title, never the host or another member', function () {
    eanAnswer('member', $this->event, 'replay');
    eanAnswer('second', $this->event, 'yes');
    $this->travel(3)->minutes();
    eanAnswer('member', $this->event, 'yes');

    $zip = new ZipArchive;
    expect($zip->open(app(ExportsUsers::class)->export($this->c['member'])))->toBeTrue();
    $activity = (string) $zip->getFromName('activity.json');
    $readme = (string) $zip->getFromName('README.txt');
    $all = '';
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $all .= (string) $zip->getFromIndex($index);
    }
    $zip->close();
    $answers = json_decode($activity, true)['event_answers'];

    expect($answers)->toHaveCount(1)
        ->and($answers[0])->toBe([
            'cuid' => EventAnswer::query()->where('user_id', $this->c['member']->id)->firstOrFail()->cuid,
            'community' => $this->c['community']->cuid,
            'community_slug' => $this->c['community']->slug,
            'space' => $this->space->cuid,
            'space_slug' => $this->space->slug,
            'event' => $this->event->cuid,
            'event_title' => $this->event->title,
            'answer' => 'yes',
            'created_at' => '2026-11-10T09:00:00+00:00',
            'updated_at' => '2026-11-10T09:03:00+00:00',
        ])
        ->and($readme)->toContain('your answers to events')
        // No other person: not the host, not the other member who answered
        ->and($all)->not->toContain($this->c['host']->cuid)
        ->not->toContain($this->c['host']->name)
        ->not->toContain($this->c['second']->cuid)
        ->not->toContain($this->c['second']->name);

    // The export of a member without an answer has an empty list
    expect($zip->open(app(ExportsUsers::class)->export($this->c['third'])))->toBeTrue();
    $none = json_decode((string) $zip->getFromName('activity.json'), true)['event_answers'];
    $zip->close();
    expect($none)->toBe([]);
});

test('the migration of the answers goes down and up again', function () {
    $migration = require database_path('migrations/2026_11_20_100000_create_event_answers_table.php');
    eanAnswer('member', $this->event, 'yes');

    $migration->down();
    expect(Schema::hasTable('event_answers'))->toBeFalse()
        ->and(Schema::hasColumn('events', 'capacity'))->toBeFalse()
        ->and(Schema::hasColumn('events', 'replay_expected'))->toBeFalse()
        ->and(EventModel::query()->whereKey($this->event->id)->exists())->toBeTrue();

    $migration->up();
    expect(Schema::hasTable('event_answers'))->toBeTrue()
        ->and(Schema::hasColumns('events', ['capacity', 'replay_expected']))->toBeTrue()
        // The index of the answers of a community: the delete of a community and the leave of a member use it
        ->and(array_column(Schema::getIndexes('event_answers'), 'columns'))->toContain(['community_id'])
        ->and(EventModel::query()->findOrFail($this->event->id)->replay_expected)->toBeFalse();
});
