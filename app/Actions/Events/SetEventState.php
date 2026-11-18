<?php

namespace App\Actions\Events;

use App\Contracts\Events\SetsEventStates;
use App\Contracts\Notifications\PrunesNotifications;
use App\Enums\KnowiiEventState;
use App\Events\Spaces\SpaceContentChanged;
use App\Exceptions\BusinessException;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The state of an event (#1513, ADR 0053). Allowed: draft to published, published to draft (not once it started),
 * published to cancelled, cancelled to published. A cancel needs the ability `cancel` (moderators of the space too), the
 * other changes need `update`. Each real change adds 1 to `sequence`; the same state again changes nothing.
 */
class SetEventState implements SetsEventStates
{
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

        $from = $event->state;
        if ($target === $from) {
            return $event;
        }
        self::assertAllowed($from, $target, $event);

        DB::transaction(function () use ($event, $target) {
            $event->state = $target;
            $event->sequence++;
            $event->save();
        });

        Log::info('Event state changed', ['event' => $event->cuid, 'from' => $from->value, 'to' => $target->value]);
        match ($target) {
            // A draft is for its host, the owner and the admins: its "new event" lines go (a second publication writes
            // none), and the readers of the space drop it from their lists
            KnowiiEventState::Draft => self::backToDraft($event, $user),
            // `space.event.created` when it leaves the draft, else `space.event.updated`
            KnowiiEventState::Published => EventPublication::published($user, $event, $from === KnowiiEventState::Draft, $notifyNow),
            KnowiiEventState::Cancelled => event(SpaceContentChanged::forEvent(SpaceContentChanged::EVENT_UPDATED, $event, $user->cuid)),
        };

        return $event;
    }

    private static function backToDraft(Event $event, User $user): void
    {
        app(PrunesNotifications::class)->forDraftEvent($event);
        event(SpaceContentChanged::forEvent(SpaceContentChanged::EVENT_DELETED, $event, $user->cuid));
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
