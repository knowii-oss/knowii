<?php

namespace App\Actions\Events;

use App\Contracts\Events\DuplicatesEvents;
use App\Enums\KnowiiEventState;
use App\Models\Event;
use App\Models\User;
use App\Support\EventTimes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * "Duplicate" for weekly events (#1513, ADR 0053, design 3.5): a draft in the same space with the same title,
 * description, zone, address, place, visibility of the address, limit of places, replay choice (#1515), cover and host,
 * 7 days later at the same wall-clock time in the zone of the event (not 168 hours later: over a change of summer time,
 * the instant moves by one hour). `sequence` starts at 0, the copy has told nobody yet, and it has no answers.
 *
 * The person who duplicates changes the event and may create one in its space (the posting policy). The copy goes
 * through the time rules of a create: a wall-clock time that the zone skips that day, a length that a change of the
 * clocks would change, and a start in the past for a person who is not the owner or an admin are field errors. A host
 * who may not host any more (who left, or cannot read the space) is replaced by the person who duplicates.
 * A draft sends nothing on the space channel.
 */
class DuplicateEvent implements DuplicatesEvents
{
    private const int DAYS_LATER = 7;

    final public function duplicate(User $user, Event $event): Event
    {
        EventPublication::assertVisible($user, $event);
        Gate::forUser($user)->authorize('update', $event);
        Gate::forUser($user)->authorize('contribute', $event->space);

        $startsAt = EventTimes::daysLaterInZone('starts_at', $event->starts_at->toImmutable(), $event->time_zone, self::DAYS_LATER);
        $endsAt = EventTimes::daysLaterInZone('ends_at', $event->ends_at->toImmutable(), $event->time_zone, self::DAYS_LATER);
        if ((int) $startsAt->diffInSeconds($endsAt) !== (int) $event->starts_at->diffInSeconds($event->ends_at)) {
            throw ValidationException::withMessages(['ends_at' => 'The clocks change during the copy, so it would not last as long. Create this event by hand.']);
        }
        CreateEvent::checkTimes($user, $event->community, $startsAt, $endsAt, true);

        $host = $event->host;
        $hostId = $host !== null && $host->belongsToCommunity($event->community) && Gate::forUser($host)->check('view', $event->space)
            ? $host->id
            : $user->id;

        $copy = DB::transaction(function () use ($event, $startsAt, $endsAt, $hostId) {
            $copy = Event::create([
                'community_id' => $event->community_id,
                'space_id' => $event->space_id,
                'host_id' => $hostId,
                'title' => $event->title,
                'body' => $event->body,
                'body_html' => $event->body_html,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'time_zone' => $event->time_zone,
                'meeting_url' => $event->meeting_url,
                'place' => $event->place,
                'location_visibility' => $event->location_visibility,
                'cover_upload_id' => $event->cover_upload_id,
                'cover_alt' => $event->cover_alt,
                // The limit of places and "a replay will exist" (#1515). The copy has no answers
                'capacity' => $event->capacity,
                // A model that was just created does not hold the default of the database (rules.md, Data 7)
                'replay_expected' => $event->replay_expected === true,
                'state' => KnowiiEventState::Draft,
                'sequence' => 0,
                'published_notified_at' => null,
            ]);
            $copy->syncUploads();

            return $copy;
        });

        Log::info('Event duplicated', ['event' => $event->cuid, 'copy' => $copy->cuid]);

        return $copy;
    }
}
