<?php

namespace App\Actions\Events;

use App\Actions\Notifications\Notify;
use App\Contracts\Notifications\Notifies;
use App\Contracts\Notifications\TracksSpaceVisits;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

/**
 * What a publication of an event starts (#1513, ADR 0053), for CreateEvent and SetEventState:
 *
 * - from a draft, the space has new content (the dot of the navigation, #1476). A draft does not;
 * - the first publication of an event whose start is in the future queues the `event_new` lines (kind 25): once, which
 *   `published_notified_at` records, and never for a past event. Inside Notify::silenced() (an import) it queues nothing
 *   but still records the publication, so a later publication tells nobody either. The fan-out waits 5 minutes unless
 *   `$notifyNow`, then reads the event again.
 * - the live update: `space.event.created` when the event leaves the draft, `space.event.updated` otherwise. A draft
 *   sends nothing on the space channel: only its host, the owner and the admins may know that it exists.
 */
final class EventPublication
{
    public static function published(User $user, Event $event, bool $fromDraft, bool $notifyNow): void
    {
        if ($fromDraft) {
            app(TracksSpaceVisits::class)->wroteIn($user, $event->space);
        }

        if ($event->published_notified_at === null && ! $event->hasStarted()) {
            $event->forceFill(['published_notified_at' => now()])->save();
            if (! Notify::isSilenced()) {
                app(Notifies::class)->notify($event, $notifyNow);
            }
        }

        event(SpaceContentChanged::forEvent($fromDraft ? SpaceContentChanged::EVENT_CREATED : SpaceContentChanged::EVENT_UPDATED, $event));
    }

    /**
     * The live update of a change of an event that is not a draft (identifiers only). A draft sends nothing.
     */
    public static function broadcast(string $name, Event $event, User $user): void
    {
        if (! $event->isDraft()) {
            event(SpaceContentChanged::forEvent($name, $event));
        }
    }

    /**
     * A person who may not see the event gets the answer of an event that does not exist (404): nothing tells them that
     * it exists, nor its state. Called first by the actions that change an event.
     *
     * @throws ModelNotFoundException<Event>
     */
    public static function assertVisible(User $user, Event $event): void
    {
        if (! Gate::forUser($user)->allows('view', $event)) {
            throw (new ModelNotFoundException)->setModel(Event::class, [$event->cuid]);
        }
    }
}
