<?php

namespace App\Actions\Events;

use App\Contracts\Events\DeletesEvents;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Delete an event (#1513, ADR 0053): the owner, the admins, the host and the moderators of the space. The database
 * deletes its upload usages and its notification rows by cascade; `uploads:prune` deletes the images that nothing else
 * uses.
 */
class DeleteEvent implements DeletesEvents
{
    final public function delete(User $user, Event $event): void
    {
        EventPublication::assertVisible($user, $event);
        Gate::forUser($user)->authorize('delete', $event);

        // Built before the delete: the broadcast needs the space of the event. A draft sends nothing on the space channel
        $broadcast = $event->isDraft() ? null : SpaceContentChanged::forEvent(SpaceContentChanged::EVENT_DELETED, $event, $user->cuid);
        $event->delete();

        Log::info('Event deleted', ['event' => $event->cuid]);
        if ($broadcast !== null) {
            event($broadcast);
        }
    }
}
