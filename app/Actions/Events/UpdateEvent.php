<?php

namespace App\Actions\Events;

use App\Contracts\Events\UpdatesEvents;
use App\Enums\KnowiiEventLocationVisibility;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\Event;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Change an event (#1513, ADR 0053): the owner, the admins and the host. Every field of a create but the state, each
 * optional; the times are checked with the stored values of the fields that are absent. The slug stays. `sequence` goes
 * up when the start, the end, the meeting address or the place changes. An update tells nobody in this slice.
 */
class UpdateEvent implements UpdatesEvents
{
    private const array SEQUENCE_FIELDS = ['starts_at', 'ends_at', 'meeting_url', 'place'];

    final public function update(User $user, Event $event, array $input): Event
    {
        Log::info('Processing request to update an event', ['event' => $event->cuid]);

        EventPublication::assertVisible($user, $event);
        Gate::forUser($user)->authorize('update', $event);

        /** @var array{title?: string, body?: string|null, starts_at?: string, ends_at?: string, time_zone?: string, meeting_url?: string|null, place?: string|null, location_visibility?: string, cover_upload_cuid?: string|null, cover_alt?: string|null, host_cuid?: string} $validated */
        $validated = Validator::make($input, [
            ...CreateEvent::rules(false),
            'host_cuid' => ['sometimes', 'string'],
        ])->validate();
        if ($validated === []) {
            return $event;
        }

        $community = $event->community;
        $changes = [];
        if (isset($validated['title'])) {
            $changes['title'] = trim($validated['title']);
        }
        if (array_key_exists('body', $validated)) {
            $body = (string) $validated['body'];
            $changes['body'] = $body;
            $changes['body_html'] = $body === '' ? '' : Markdown::render($body);
        }
        if (isset($validated['starts_at']) || isset($validated['ends_at']) || isset($validated['time_zone'])) {
            $times = CreateEvent::times($user, $community, $validated, $event);
            $changes['starts_at'] = $times['starts_at'];
            $changes['ends_at'] = $times['ends_at'];
            $changes['time_zone'] = $times['time_zone'];
        }
        foreach (['meeting_url', 'place', 'cover_alt'] as $field) {
            if (array_key_exists($field, $validated)) {
                $changes[$field] = CreateEvent::blankToNull($validated[$field]);
            }
        }
        if (isset($validated['location_visibility'])) {
            $changes['location_visibility'] = KnowiiEventLocationVisibility::from($validated['location_visibility']);
        }
        if (array_key_exists('cover_upload_cuid', $validated)) {
            $changes['cover_upload_id'] = CreateEvent::coverId($community, $validated['cover_upload_cuid']);
        }
        if (isset($validated['host_cuid'])) {
            $changes['host_id'] = CreateEvent::host($event->space, $validated['host_cuid'])->id;
        }

        DB::transaction(function () use ($event, $changes) {
            $event->fill($changes);
            if ($event->isDirty(self::SEQUENCE_FIELDS)) {
                $event->sequence++;
            }
            $syncUploads = $event->isDirty(['body', 'cover_upload_id']);
            $event->save();

            if ($syncUploads) {
                $event->syncUploads();
            }
        });

        Log::info('Event updated', ['event' => $event->cuid, 'fields' => array_keys($validated)]);
        // A draft sends nothing on the space channel
        EventPublication::broadcast(SpaceContentChanged::EVENT_UPDATED, $event, $user);

        return $event;
    }
}
