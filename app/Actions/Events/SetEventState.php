<?php

namespace App\Actions\Events;

use App\Contracts\Events\SetsEventStates;
use App\Contracts\Notifications\PrunesNotifications;
use App\Enums\KnowiiEventState;
use App\Events\Spaces\SpaceContentChanged;
use App\Exceptions\BusinessException;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The state of an event (#1513, ADR 0053). Allowed: draft to published, published to draft (not once it started, not
 * once a member who can read the space answered it, #1515, and not once it has a comment, #1516), published to
 * cancelled, cancelled to published (the answers are still there). A cancel needs the ability `cancel` (moderators of
 * the space too), the other changes need `update`. Each real change adds 1 to `sequence`; the same state again changes nothing.
 *
 * The change is decided inside the lock of the row of the event, from the state and the start of that row: two changes
 * of one event run one after the other, and the second one sees what the first one wrote.
 */
class SetEventState implements SetsEventStates
{
    public const string ANSWERED_MESSAGE = 'Members answered this event. Cancel it instead.';

    public const string COMMENTED_MESSAGE = 'Members commented on this event. Cancel it instead.';

    final public function setState(User $user, Event $event, array $input): Event
    {
        Log::info('Processing request to change the state of an event', ['event' => $event->cuid]);

        // A person who may not see the event learns nothing of it, its state included
        EventPublication::assertVisible($user, $event);
        // Every person who changes the event can cancel it: the narrower right comes after the validation
        Gate::forUser($user)->authorize('cancel', $event);

        /** @var array{state: string, notify_now?: bool} $validated */
        $validated = Validator::make($input, [
            'state' => ['required', Rule::enum(KnowiiEventState::class)],
            'notify_now' => ['sometimes', 'boolean'],
        ])->validate();

        $target = KnowiiEventState::from($validated['state']);
        if ($target !== KnowiiEventState::Cancelled) {
            Gate::forUser($user)->authorize('update', $event);
        }

        // "Send now" skips the 5 minutes of the fan-out: the people who moderate the space only, as for a post
        $notifyNow = ($validated['notify_now'] ?? false) === true;
        if ($notifyNow) {
            Gate::forUser($user)->authorize('moderate', $event->space);
        }

        $from = DB::transaction(function () use ($user, $event, $target): ?KnowiiEventState {
            // A change of state waits for the answers that are being written, and they wait for it (#1515): an answer
            // never passes a cancel that runs at the same moment. The lock of the row of the event, as in AnswerEvent
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->first()
                ?? throw (new ModelNotFoundException)->setModel(Event::class, [$event->cuid]);
            // The row as it is now decides, not what the request loaded before the lock: another request may have
            // changed the state or the start since. The model of the request takes the values of the locked row
            $event->setRawAttributes($locked->getAttributes(), true);
            self::assertMay($user, $event, $target);

            $from = $event->state;
            if ($target === $from) {
                return null;
            }
            self::assertAllowed($from, $target, $event);
            // "Nobody answered" counts the answers of the members who can read the space today, and a "No" is an answer
            // too: these members must find the event again. The row of a member who lost the access stays with the draft
            if ($from === KnowiiEventState::Published && $target === KnowiiEventState::Draft && $event->answers()->ofReaders()->exists()) {
                throw new BusinessException(self::ANSWERED_MESSAGE);
            }
            // A draft never holds a comment (#1516): a reply or a mention in it would tell a member who cannot see it. A
            // marker counts too. A new comment waits for this lock (CreateComment), so none comes in between
            if ($from === KnowiiEventState::Published && $target === KnowiiEventState::Draft && $event->comments()->exists()) {
                throw new BusinessException(self::COMMENTED_MESSAGE);
            }
            $event->state = $target;
            $event->sequence++;
            $event->save();

            return $from;
        });
        // The same state again changes nothing
        if ($from === null) {
            return $event;
        }

        Log::info('Event state changed', ['event' => $event->cuid, 'from' => $from->value, 'to' => $target->value]);
        match ($target) {
            // A draft is for its host, the owner and the admins: its "new event" lines go (a second publication writes
            // none), and the readers of the space drop it from their lists
            KnowiiEventState::Draft => self::backToDraft($event, $user),
            // `space.event.created` when it leaves the draft, else `space.event.updated`
            KnowiiEventState::Published => EventPublication::published($user, $event, $from === KnowiiEventState::Draft, $notifyNow),
            KnowiiEventState::Cancelled => event(SpaceContentChanged::forEvent(SpaceContentChanged::EVENT_UPDATED, $event)),
        };

        return $event;
    }

    /**
     * The person sees the event and may make this change (a cancel needs `cancel`, the other changes need `update`), asked
     * again inside the lock with the row as it is then: a draft is for fewer people than a published event
     */
    private static function assertMay(User $user, Event $event, KnowiiEventState $target): void
    {
        EventPublication::assertVisible($user, $event);
        Gate::forUser($user)->authorize($target === KnowiiEventState::Cancelled ? 'cancel' : 'update', $event);
    }

    private static function backToDraft(Event $event, User $user): void
    {
        app(PrunesNotifications::class)->forDraftEvent($event);
        event(SpaceContentChanged::forEvent(SpaceContentChanged::EVENT_DELETED, $event));
    }

    private static function assertAllowed(KnowiiEventState $from, KnowiiEventState $to, Event $event): void
    {
        match (true) {
            $from === KnowiiEventState::Draft && $to === KnowiiEventState::Cancelled => throw new BusinessException('A draft cannot be cancelled. Delete it instead.'),
            $from === KnowiiEventState::Cancelled && $to === KnowiiEventState::Draft => throw new BusinessException('A cancelled event cannot go back to draft. Publish it again instead.'),
            $from === KnowiiEventState::Published && $to === KnowiiEventState::Draft && $event->hasStarted() => throw new BusinessException('An event that started cannot go back to draft. Cancel it instead.'),
            default => null,
        };
    }
}
