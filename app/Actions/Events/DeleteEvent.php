<?php

namespace App\Actions\Events;

use App\Contracts\Events\DeletesEvents;
use App\Enums\KnowiiEventAnswer;
use App\Enums\KnowiiEventState;
use App\Events\Spaces\SpaceContentChanged;
use App\Exceptions\BusinessException;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Delete an event (#1513, ADR 0053): the owner, the admins, the host and the moderators of the space. The database
 * deletes its answers, its comments, its upload usages and its notification rows by cascade; `uploads:prune` deletes the
 * images that nothing else uses.
 *
 * Cancel it first (#1516, design 7.2 point 3, decision 17): a published event whose end is not passed (an event that
 * happens now too) and that a member who can read the space today answered Yes or Replay only is refused, so that the
 * members are told by the cancel. The rule is read from the row of the event in a lock, as SetEventState does: a change
 * of state or an answer that runs at the same moment comes before or after it, never in between.
 */
class DeleteEvent implements DeletesEvents
{
    public const string CANCEL_FIRST_MESSAGE = 'Cancel it first, so members are told.';

    final public function delete(User $user, Event $event): void
    {
        EventPublication::assertVisible($user, $event);
        Gate::forUser($user)->authorize('delete', $event);

        // Built before the delete: the broadcast needs the space of the event. A draft sends nothing on the space channel
        $broadcast = null;
        DB::transaction(function () use ($event, $user, &$broadcast) {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if (self::mustCancelFirst($locked)) {
                throw new BusinessException(self::CANCEL_FIRST_MESSAGE);
            }
            $broadcast = $locked->isDraft() ? null : SpaceContentChanged::forEvent(SpaceContentChanged::EVENT_DELETED, $event, $user->cuid);
            $locked->delete();
        });

        Log::info('Event deleted', ['event' => $event->cuid]);
        if ($broadcast !== null) {
            event($broadcast);
        }
    }

    /**
     * Whether a delete of the event is refused: published, its end now or later, and a Yes or a Replay only answer of a
     * member who can read the space today. A No alone does not block it, nor the answer of a member who lost the access.
     */
    public static function mustCancelFirst(Event $event): bool
    {
        return $event->state === KnowiiEventState::Published
            && ! $event->isPast()
            && $event->answers()->ofReaders()->whereIn('answer', [KnowiiEventAnswer::Yes->value, KnowiiEventAnswer::Replay->value])->exists();
    }
}
