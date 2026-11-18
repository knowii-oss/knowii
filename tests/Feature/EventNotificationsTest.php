<?php

use App\Actions\Notifications\Notify;
use App\ApiRoutes;
use App\Contracts\Events\CreatesEvents;
use App\Contracts\Events\SetsEventStates;
use App\Contracts\Events\UpdatesEvents;
use App\Contracts\Notifications\ChoosesSpaceLevels;
use App\Contracts\Notifications\Notifies;
use App\Contracts\Users\BlocksMembers;
use App\Contracts\Users\ExportsUsers;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiNotificationKind;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceTreeChanged;
use App\Jobs\FanOutNotifications;
use App\Models\Event as EventModel;
use App\Models\Notification;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/*
 * The line of kind 25, `event_new` (#1513, ADR 0053, design 8.1): the first publication of an event tells the members of
 * the events space at "Everything" (its built-in default), after 5 minutes, once.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10T09:00:00Z'));
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $this->host = $this->c['moderator'];
    $this->reader = User::factory()->withUserProfile()->create();
    $this->c['community']->users()->attach($this->reader, ['role' => KnowiiCommunityMemberRole::Member]);
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->events = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Events, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
});

/**
 * @param  array<string, mixed>  $changes
 */
function evnPublish(User $host, Space $space, array $changes = []): EventModel
{
    return app(CreatesEvents::class)->create($host, $space, [
        'title' => 'Weekly review office hour',
        'body' => 'Bring your questions about the weekly review',
        'starts_at' => '2026-11-18T17:00:00+01:00',
        'ends_at' => '2026-11-18T18:00:00+01:00',
        'time_zone' => 'Europe/Brussels',
        'state' => 'published',
        ...$changes,
    ]);
}

/**
 * The event_new rows, by recipient id
 *
 * @return Collection<int, Notification>
 */
function evnRows(?EventModel $event = null): Collection
{
    return Notification::query()
        ->where('kind', KnowiiNotificationKind::EventNew->value)
        ->when($event !== null, fn ($query) => $query->where('event_id', $event?->id))
        ->orderBy('user_id')
        ->get()
        ->keyBy('user_id');
}

test('the first publication writes one line per member at Everything, never for the host', function () {
    $event = evnPublish($this->host, $this->events);

    $rows = evnRows($event);
    expect($rows->keys()->sort()->values()->all())->toBe(collect([$this->c['owner']->id, $this->c['admin']->id, $this->c['member']->id, $this->reader->id])->sort()->values()->all())
        ->and($rows->has($this->host->id))->toBeFalse()
        ->and($rows->has($this->c['outsider']->id))->toBeFalse();
    $row = $rows->get($this->c['member']->id);
    expect($row->actor_id)->toBe($this->host->id)
        ->and($row->space_id)->toBe($this->events->id)
        ->and($row->kind->level())->toBe(2)
        ->and($row->dedupe_key)->toBe('event_new:event:'.$event->id)
        ->and($event->refresh()->published_notified_at)->not->toBeNull();
});

test('the line holds the title, an excerpt, the start of the event and its target; the reason is Everything', function () {
    $event = evnPublish($this->host, $this->events);

    $item = $this->actingAs($this->c['member'])->getJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS).'?level=2')->assertOk()->json('data.0');

    expect($item['kind'])->toBe('event_new')
        ->and($item['level'])->toBe(2)
        ->and($item['reason'])->toBe('space_everything')
        ->and($item['title'])->toBe('Weekly review office hour')
        ->and($item['excerpt'])->toBe('Bring your questions about the weekly review')
        ->and($item['event_starts_at'])->toBe('2026-11-18T16:00:00.000000Z')
        ->and($item['target']['event_cuid'])->toBe($event->cuid)
        ->and($item['target']['event_slug'])->toBe($event->slug)
        ->and($item['target']['post_slug'])->toBeNull()
        ->and($item['actor']['cuid'])->toBe($this->host->cuid)
        ->and($item['items_count'])->toBe(1)
        ->and($item['space']['cuid'])->toBe($this->events->cuid);
    // The other kinds have no start
    expect(Notification::factory()->make(['kind' => KnowiiNotificationKind::CommentReply])->toResource()->resolve()['event_starts_at'])->toBeNull();
});

test('the fan-out waits 5 minutes, unless notify_now, which only the moderators of the space may ask', function () {
    Queue::fake();
    evnPublish($this->host, $this->events);
    evnPublish($this->host, $this->events, ['notify_now' => true]);

    $jobs = Queue::pushed(FanOutNotifications::class)->values();
    expect($jobs)->toHaveCount(2)
        ->and($jobs[0]->delay)->toBeInstanceOf(DateTimeInterface::class)
        ->and($jobs[0]->delay->getTimestamp())->toBe(now()->addMinutes(5)->getTimestamp())
        ->and($jobs[1]->delay)->toBeNull();

    // A member who may create events in a members space, but does not moderate it
    $open = Space::factory()->inGroup($this->events->spaceGroup)->create(['type' => KnowiiSpaceType::Events, 'posting_policy' => KnowiiSpacePostingPolicy::Members]);
    expect(fn () => evnPublish($this->c['member'], $open, ['notify_now' => true]))->toThrow(AuthorizationException::class);
});

test('notify_now on the state route skips the wait too, and needs the moderation of the space', function () {
    Queue::fake();
    $draft = evnPublish($this->host, $this->events, ['state' => 'draft']);
    $other = evnPublish($this->c['admin'], $this->events, ['state' => 'draft']);
    expect(Queue::pushed(FanOutNotifications::class))->toHaveCount(0);

    app(SetsEventStates::class)->setState($this->host, $draft, ['state' => 'published', 'notify_now' => true]);
    expect(Queue::pushed(FanOutNotifications::class)->first()->delay)->toBeNull();

    // The host of an event in a space they do not moderate
    $this->c['community']->update(['moderator_permissions' => ['delete_content' => false]]);
    $hostWithoutRights = User::findOrFail($this->host->id);
    $other->update(['host_id' => $hostWithoutRights->id]);
    $other = EventModel::findOrFail($other->id);
    expect(fn () => app(SetsEventStates::class)->setState($hostWithoutRights, $other, ['state' => 'published', 'notify_now' => true]))->toThrow(AuthorizationException::class);
});

test('after the wait, the job reads the event again: an event that went back to draft or started tells nobody', function () {
    Queue::fake();
    $event = evnPublish($this->host, $this->events);
    $started = evnPublish($this->host, $this->events, ['title' => 'Starts soon', 'starts_at' => '2026-11-10T10:02:00+01:00', 'ends_at' => '2026-11-10T11:00:00+01:00']);
    [$first, $second] = Queue::pushed(FanOutNotifications::class)->values()->all();

    app(SetsEventStates::class)->setState($this->host, $event, ['state' => 'draft']);
    $this->travel(6)->minutes();
    $first->handle(app(Notifies::class));
    $second->handle(app(Notifies::class));

    expect(evnRows())->toHaveCount(0);
});

test('a draft tells nobody, and its publication does', function () {
    $draft = evnPublish($this->host, $this->events, ['state' => 'draft']);
    expect(evnRows())->toHaveCount(0)->and($draft->published_notified_at)->toBeNull();

    // A draft can be old: the age guard of the other items does not apply
    $this->travel(2)->days();
    app(SetsEventStates::class)->setState($this->host, $draft, ['state' => 'published']);

    expect(evnRows($draft))->toHaveCount(4);
});

test('a member who blocked the host, who muted the space or who is at "Only what is for me" gets nothing', function () {
    app(BlocksMembers::class)->block($this->c['member'], $this->host);
    app(ChoosesSpaceLevels::class)->set($this->c['admin'], $this->events, ['level' => 'none']);
    app(ChoosesSpaceLevels::class)->set($this->reader, $this->events, ['level' => 'mine']);

    $event = evnPublish($this->host, $this->events);

    expect(evnRows($event)->keys()->all())->toBe([$this->c['owner']->id]);
});

test('with "Only what is for me" as default of the space, only the members who chose Everything are told', function () {
    $this->events->update(['default_notification_level' => 'mine']);
    app(ChoosesSpaceLevels::class)->set($this->reader, $this->events, ['level' => 'all']);

    $event = evnPublish($this->host, $this->events);

    expect(evnRows($event)->keys()->all())->toBe([$this->reader->id]);
});

test('a past event, an event without a host and an event published inside Notify::silenced() tell nobody', function () {
    $past = evnPublish($this->c['admin'], $this->events, ['starts_at' => '2026-11-03T17:00:00+01:00', 'ends_at' => '2026-11-03T18:00:00+01:00']);
    $silent = Notify::silenced(fn () => evnPublish($this->host, $this->events, ['title' => 'Imported']));

    expect(evnRows())->toHaveCount(0)
        ->and($past->published_notified_at)->toBeNull()
        // An import still records the publication: a later publication tells nobody either
        ->and($silent->published_notified_at)->not->toBeNull();

    app(SetsEventStates::class)->setState($this->host, $silent, ['state' => 'draft']);
    app(SetsEventStates::class)->setState($this->host, $silent, ['state' => 'published']);
    expect(evnRows($silent))->toHaveCount(0);

    // A host whose account was deleted while the job waited
    Queue::fake();
    $orphan = evnPublish($this->host, $this->events, ['title' => 'Orphan']);
    $job = Queue::pushed(FanOutNotifications::class)->first();
    $orphan->update(['host_id' => null]);
    $job->handle(app(Notifies::class));
    expect(evnRows($orphan))->toHaveCount(0);
});

test('a second publication writes no new line, and a retry of the job writes no second row', function () {
    $event = evnPublish($this->host, $this->events);
    expect(evnRows($event))->toHaveCount(4);

    app(SetsEventStates::class)->setState($this->host, $event, ['state' => 'cancelled']);
    app(SetsEventStates::class)->setState($this->host, $event, ['state' => 'published']);
    expect(evnRows($event))->toHaveCount(4);

    // The same job again (a retry)
    (new FanOutNotifications($event->refresh()))->handle(app(Notifies::class));
    expect(Notification::query()->where('event_id', $event->id)->count())->toBe(4);
});

test('back to draft deletes the lines of the event, and the next publication writes none', function () {
    $event = evnPublish($this->host, $this->events);
    $other = evnPublish($this->host, $this->events, ['title' => 'Another one']);

    app(SetsEventStates::class)->setState($this->host, $event, ['state' => 'draft']);
    expect(evnRows($event))->toHaveCount(0)->and(evnRows($other))->toHaveCount(4);

    app(SetsEventStates::class)->setState($this->host, $event, ['state' => 'published']);
    expect(evnRows($event))->toHaveCount(0);
});

test('an update tells nobody', function () {
    $event = evnPublish($this->host, $this->events, ['state' => 'draft']);
    app(UpdatesEvents::class)->update($this->host, $event, ['title' => 'New title', 'starts_at' => '2026-11-19T17:00:00+01:00', 'ends_at' => '2026-11-19T18:00:00+01:00']);

    expect(Notification::query()->count())->toBe(0);
});

test('the rows of an event do not bundle: one row per member and event', function () {
    evnPublish($this->host, $this->events);
    evnPublish($this->host, $this->events, ['title' => 'Second']);

    expect(Notification::query()->where('user_id', $this->c['member']->id)->count())->toBe(2);
    $items = $this->actingAs($this->c['member'])->getJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS).'?level=2')->assertOk()->json('data');
    expect(array_column($items, 'items_count'))->toBe([1, 1]);
});

test('blocking the host deletes the lines of their events', function () {
    evnPublish($this->host, $this->events);

    app(BlocksMembers::class)->block($this->c['member'], $this->host);

    expect(Notification::query()->where('user_id', $this->c['member']->id)->count())->toBe(0)
        ->and(Notification::query()->where('user_id', $this->c['owner']->id)->count())->toBe(1);
});

test('the export of the member names the event of a line by its cuid', function () {
    $event = evnPublish($this->host, $this->events);

    $zip = new ZipArchive;
    expect($zip->open(app(ExportsUsers::class)->export($this->c['member'])))->toBeTrue();
    $json = '';
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $json .= (string) $zip->getFromIndex($index);
    }
    $zip->close();

    expect($json)->toContain('event_new')->toContain($event->cuid);
});
