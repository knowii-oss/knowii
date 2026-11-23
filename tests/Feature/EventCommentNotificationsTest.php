<?php

use App\ApiRoutes;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Events\UpdatesEvents;
use App\Contracts\Users\BlocksMembers;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiNotificationKind;
use App\Enums\KnowiiSpaceNotificationLevel;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\Comment;
use App\Models\Event as EventModel;
use App\Models\Notification;
use App\Models\NotificationSpaceLevel;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
 * The notifications of the comments of an event, slice E3 (#1516, ADR 0053, "Comments and after the event"): a
 * first-level comment tells the host (`event_comment`, level 1, reason `hosts_event`), a reply and a mention work as for
 * a resource, the rows bundle per event, and a comment of an event writes no row of level 2 and no follow.
 *
 * The queue is "sync" in the tests: the jobs run when the caller commits.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10T09:00:00Z'));
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    foreach (['host', 'second'] as $key) {
        $this->c[$key] = User::factory()->withUserProfile()->create();
        $this->c['community']->users()->attach($this->c[$key], ['role' => KnowiiCommunityMemberRole::Member]);
    }
    $this->c['member']->forceFill(['username' => 'ecn-member'])->save();
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->space = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'name' => 'Office hours', 'visibility' => KnowiiSpaceVisibility::Members]);
    $this->event = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create(['title' => 'Weekly review']);
});

function ecnComment(string $author, EventModel $event, string $body = 'A comment without mentions', ?Comment $parent = null): Comment
{
    return app(CreatesComments::class)->create(User::query()->findOrFail(test()->c[$author]->id), $event, ['body' => $body, 'parent_cuid' => $parent?->cuid]);
}

/**
 * @return Collection<int, Notification>
 */
function ecnRows(string $key): Collection
{
    return Notification::query()->where('user_id', test()->c[$key]->id)->orderBy('id')->get();
}

/**
 * The first line of the list of the member, as the API gives it
 *
 * @return array<string, mixed>
 */
function ecnLine(string $key): array
{
    return test()->actingAs(User::query()->findOrFail(test()->c[$key]->id))->getJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS))->assertOk()->json('data.0');
}

test('the kind of a comment on my event is level 1, with the reason that I host the event', function () {
    expect(KnowiiNotificationKind::EventComment->value)->toBe('event_comment')
        ->and(KnowiiNotificationKind::EventComment->level())->toBe(1)
        ->and(KnowiiNotificationKind::EventComment->reason()->value)->toBe('hosts_event');
});

test('a first-level comment on an event tells its host, with the title, the target and the excerpt of the row', function () {
    $comment = ecnComment('member', $this->event, "Thank you for the **session**\n\nSee you next week");

    $rows = ecnRows('host');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->kind)->toBe(KnowiiNotificationKind::EventComment)
        ->and($rows[0]->comment_id)->toBe($comment->id)
        ->and($rows[0]->space_id)->toBe($this->space->id)
        ->and($rows[0]->dedupe_key)->toBe('event_comment:event:'.$this->event->id);

    $line = ecnLine('host');
    expect($line['kind'])->toBe('event_comment')
        ->and($line['level'])->toBe(1)
        ->and($line['reason'])->toBe('hosts_event')
        ->and($line['title'])->toBe('Weekly review')
        ->and($line['excerpt'])->toBe('Thank you for the session See you next week')
        ->and($line['space'])->toBe(['cuid' => $this->space->cuid, 'slug' => $this->space->slug, 'name' => 'Office hours'])
        ->and($line['target'])->toBe(['post_slug' => null, 'post_cuid' => null, 'comment_cuid' => $comment->cuid, 'chat_message_cuid' => null, 'thread_cuid' => null, 'resource_cuid' => null, 'resource_slug' => null, 'page_slug' => null, 'event_cuid' => $this->event->cuid, 'event_slug' => $this->event->slug])
        ->and($line['event_starts_at'])->toBeNull();
});

test('two comments of two members on one event are one row for the host, with 2 in items_count', function () {
    ecnComment('member', $this->event, 'First');
    ecnComment('second', $this->event, 'Second');

    expect(ecnRows('host'))->toHaveCount(1);
    $line = ecnLine('host');
    expect($line['items_count'])->toBe(2)
        ->and(array_column($line['actors'], 'cuid'))->toBe([$this->c['second']->cuid, $this->c['member']->cuid]);
});

test('two events never share a row', function () {
    $other = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create(['title' => 'Another session']);

    ecnComment('member', $this->event, 'First');
    ecnComment('member', $other, 'Second');

    $rows = ecnRows('host');
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('dedupe_key')->all())->toBe(['event_comment:event:'.$this->event->id, 'event_comment:event:'.$other->id]);
});

test('the host gets no row for their own comment, when they blocked the author, in a muted space, or when they left', function (string $case) {
    match ($case) {
        'own comment' => null,
        'blocked' => app(BlocksMembers::class)->block($this->c['host'], $this->c['member']),
        'muted' => NotificationSpaceLevel::query()->create(['user_id' => $this->c['host']->id, 'space_id' => $this->space->id, 'level' => KnowiiSpaceNotificationLevel::None]),
        'left' => $this->c['community']->removeUser($this->c['host']),
    };

    ecnComment($case === 'own comment' ? 'host' : 'member', $this->event);

    expect(ecnRows('host'))->toHaveCount(0);
})->with(['own comment', 'blocked', 'muted', 'left']);

test('a host who cannot read the space any more gets no row', function () {
    $space = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $buyers = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Buyers']);
    $space->accessGroups()->attach($buyers);
    $buyers->members()->attach([$this->c['host']->id, $this->c['member']->id]);
    $event = EventModel::factory()->inSpace($space)->hostedBy($this->c['host'])->create();
    $buyers->members()->detach($this->c['host']->id);

    ecnComment('member', $event);

    expect(ecnRows('host'))->toHaveCount(0);
});

test('a reply on an event tells the author of the comment, and a mention tells the mentioned member', function () {
    $question = ecnComment('second', $this->event, 'A question');
    $reply = ecnComment('admin', $this->event, 'An answer', $question);
    $mention = ecnComment('admin', $this->event, 'Look, @ecn-member');

    expect(ecnRows('second')->map(fn (Notification $row) => [$row->kind, $row->comment_id])->all())->toBe([[KnowiiNotificationKind::CommentReply, $reply->id]])
        ->and(ecnRows('member')->map(fn (Notification $row) => [$row->kind, $row->comment_id])->all())->toBe([[KnowiiNotificationKind::Mention, $mention->id]]);
    $line = ecnLine('second');
    expect($line['kind'])->toBe('comment_reply')
        ->and($line['title'])->toBe('Weekly review')
        ->and($line['excerpt'])->toBe('An answer')
        ->and($line['target'])->toMatchArray(['comment_cuid' => $reply->cuid, 'event_cuid' => $this->event->cuid, 'event_slug' => $this->event->slug, 'post_slug' => null]);
    $line = ecnLine('member');
    expect($line['kind'])->toBe('mention')
        ->and($line['title'])->toBe('Weekly review')
        ->and($line['target'])->toMatchArray(['comment_cuid' => $mention->cuid, 'event_cuid' => $this->event->cuid]);
    // The host is told about the first-level comments only: the reply is not one
    expect(ecnRows('host')->pluck('kind')->unique()->all())->toBe([KnowiiNotificationKind::EventComment]);
});

test('the order of the reasons stays mention, then reply, then comment', function () {
    $this->c['host']->forceFill(['username' => 'ecn-host'])->save();

    // A first-level comment that mentions the host: one row, the mention
    $comment = ecnComment('member', $this->event, 'Hello @ecn-host');
    // A reply to the comment of the host that mentions them: one row, the mention
    $own = ecnComment('host', $this->event, 'My own comment');
    $reply = ecnComment('second', $this->event, 'Hey @ecn-host', $own);

    expect(ecnRows('host')->map(fn (Notification $row) => [$row->kind, $row->comment_id])->all())->toBe([
        [KnowiiNotificationKind::Mention, $comment->id],
        [KnowiiNotificationKind::Mention, $reply->id],
    ]);
});

test('a comment on an event writes no row of level 2 and no follow', function () {
    NotificationSpaceLevel::query()->create(['user_id' => $this->c['second']->id, 'space_id' => $this->space->id, 'level' => KnowiiSpaceNotificationLevel::All]);

    ecnComment('member', $this->event, 'First');
    ecnComment('admin', $this->event, 'Second');

    expect(Notification::query()->whereIn('kind', KnowiiNotificationKind::valuesOfLevel(2))->count())->toBe(0)
        ->and(ecnRows('second'))->toHaveCount(0)
        ->and(DB::table('notification_follows')->count())->toBe(0);
});

test('after a change of host, the former host keeps no row of the event and gets no new count; the new host gets the row', function () {
    ecnComment('member', $this->event, 'Before the change');
    expect(ecnRows('host'))->toHaveCount(1);
    $other = EventModel::factory()->inSpace($this->space)->hostedBy($this->c['host'])->create();
    ecnComment('member', $other, 'On another event of the same host');

    app(UpdatesEvents::class)->update(User::query()->findOrFail($this->c['admin']->id), $this->event, ['host_cuid' => $this->c['second']->cuid]);
    ecnComment('admin', $this->event, 'After the change');

    // The row of the other event stays: the host still hosts it
    expect(ecnRows('host')->pluck('dedupe_key')->all())->toBe(['event_comment:event:'.$other->id])
        ->and(ecnRows('second')->pluck('kind')->all())->toBe([KnowiiNotificationKind::EventComment]);
    $line = ecnLine('second');
    expect($line['items_count'])->toBe(1)
        ->and($line['excerpt'])->toBe('After the change');
});
