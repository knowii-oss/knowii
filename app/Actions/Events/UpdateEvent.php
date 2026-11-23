<?php

namespace App\Actions\Events;

use App\Constants;
use App\Contracts\Events\UpdatesEvents;
use App\Contracts\Notifications\PrunesNotifications;
use App\Enums\KnowiiEventLocationVisibility;
use App\Enums\KnowiiEventState;
use App\Events\Spaces\SpaceContentChanged;
use App\Exceptions\BusinessException;
use App\Models\Event;
use App\Models\User;
use App\Rules\MaxMarkdownImages;
use App\Support\Markdown;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Change an event (#1513, ADR 0053): the owner, the admins and the host. Every field of a create but the state, each
 * optional; the times are checked with the stored values of the fields that are absent. The slug stays. `sequence` goes
 * up when the start, the end, the meeting address or the place changes. An update tells nobody in this slice. The limit
 * of places and "a replay will exist" change here too (#1515): no answer is removed by a change.
 *
 * After the event (#1516): the replay link and the notes. A value that is not empty is accepted only when the event,
 * after the change, is published and its end is before now; an empty value (null or '') clears the field at any time.
 * They do not move `sequence`, and the server never opens the replay link.
 */
class UpdateEvent implements UpdatesEvents
{
    private const array SEQUENCE_FIELDS = ['starts_at', 'ends_at', 'meeting_url', 'place'];

    public const string REPLAY_TOO_EARLY_MESSAGE = 'Add the replay once the event is published and over.';

    public const string NOTES_TOO_EARLY_MESSAGE = 'Add the notes once the event is published and over.';

    public const string NOT_HAPPENED_MESSAGE = 'This event did not happen yet. Cancel it instead.';

    public const string MOVE_WITH_REPLAY_MESSAGE = 'Remove the replay link and the notes before you move this event to a later time.';

    final public function update(User $user, Event $event, array $input): Event
    {
        Log::info('Processing request to update an event', ['event' => $event->cuid]);

        EventPublication::assertVisible($user, $event);
        Gate::forUser($user)->authorize('update', $event);

        /** @var array{title?: string, body?: string|null, starts_at?: string, ends_at?: string, time_zone?: string, meeting_url?: string|null, place?: string|null, location_visibility?: string, cover_upload_cuid?: string|null, cover_alt?: string|null, capacity?: int|string|null, replay_expected?: bool|int|string, host_cuid?: string, replay_url?: string|null, notes?: string|null} $validated */
        $validated = Validator::make($input, [
            ...CreateEvent::rules(false),
            'host_cuid' => ['sometimes', 'string'],
            // After the event (#1516): not in the rules of a create, so a create and a duplicate ignore them
            'replay_url' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:'.Constants::MAX_LENGTH_EVENT_REPLAY_URL],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.Constants::MAX_LENGTH_EVENT_NOTES, new MaxMarkdownImages],
        ])->validate();
        if ($validated === []) {
            return $event;
        }

        $community = $event->community;
        $formerHostId = $event->host_id;
        $newEndsAt = null;
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
            $newEndsAt = $times['ends_at'];
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
        // A limit under the number of Yes answers is accepted: nobody is removed, and no new Yes passes
        if (array_key_exists('capacity', $validated)) {
            $changes['capacity'] = CreateEvent::capacity($validated['capacity']);
        }
        // Without a replay, the "Replay only" answers stay: their members choose again when they want
        if (array_key_exists('replay_expected', $validated)) {
            $changes['replay_expected'] = filter_var($validated['replay_expected'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('cover_upload_cuid', $validated)) {
            $changes['cover_upload_id'] = CreateEvent::coverId($community, $validated['cover_upload_cuid']);
        }
        if (isset($validated['host_cuid'])) {
            $changes['host_id'] = CreateEvent::host($event->space, $validated['host_cuid'])->id;
        }
        // The replay link and the notes (#1516): an empty value clears; any other value needs a published event that ended.
        // The check reads the locked row (below)
        if (array_key_exists('replay_url', $validated)) {
            $changes['replay_url'] = CreateEvent::blankToNull($validated['replay_url']);
        }
        if (array_key_exists('notes', $validated)) {
            $notes = (string) $validated['notes'];
            $changes['notes'] = trim($notes) === '' ? '' : $notes;
            // Raw HTML escaped (ADR 0013), as the description: a mention is a link and tells nobody
            $changes['notes_html'] = $changes['notes'] === '' ? '' : Markdown::render($changes['notes']);
        }

        // What the change gives, for the rules that read the locked row: the new end (above), and the replay link and the
        // notes when the request sends them (an array of one value, so that null and '' stay values)
        $replay = array_key_exists('replay_url', $changes) ? [$changes['replay_url']] : null;
        $notes = array_key_exists('notes', $changes) ? [$changes['notes']] : null;

        DB::transaction(function () use ($event, $changes, $newEndsAt, $replay, $notes) {
            // A change of the limit waits for the answers that are being written, and they wait for it (#1515): the lock
            // of the row of the event, as in AnswerEvent. The times, the replay link and the notes (#1516) are decided
            // from the locked row: a change of state that runs at the same moment comes before or after, never between
            if (array_intersect(['capacity', 'starts_at', 'replay_url', 'notes'], array_keys($changes)) !== []) {
                self::assertAfterTheEvent(Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail(), $newEndsAt, $replay, $notes);
            }
            $event->fill($changes);
            if ($event->isDirty(self::SEQUENCE_FIELDS)) {
                $event->sequence++;
            }
            $syncUploads = $event->isDirty(['body', 'notes', 'cover_upload_id']);
            $event->save();

            if ($syncUploads) {
                $event->syncUploads();
            }
        });

        Log::info('Event updated', ['event' => $event->cuid, 'fields' => array_keys($validated)]);
        // A draft sends nothing on the space channel
        EventPublication::broadcast(SpaceContentChanged::EVENT_UPDATED, $event, $user);
        // Another host (#1516): the rows "commented on your event" of the former host go; the new host gets the next ones
        if ($formerHostId !== null && $formerHostId !== $event->host_id) {
            app(PrunesNotifications::class)->forFormerHost($event, $formerHostId);
        }

        return $event;
    }

    /**
     * The rules of #1516, from the locked row and the values after the change:
     *
     * 1. A published event that did not start cannot get an end in the past: it did not happen (422). One that started
     *    may end earlier than planned.
     * 2. An event that is over and keeps a replay link or notes cannot be moved so that it is not over any more (422), in
     *    every state.
     * 3. A replay link or notes that are not empty need a published event that is over (a field error).
     *
     * @param  array{0: ?string}|null  $replay  The replay link that the request sends, else null
     * @param  array{0: string}|null  $notes  The notes that the request sends, else null
     *
     * @throws BusinessException
     * @throws ValidationException
     */
    private static function assertAfterTheEvent(Event $locked, ?CarbonImmutable $newEndsAt, ?array $replay, ?array $notes): void
    {
        $published = $locked->state === KnowiiEventState::Published;
        $over = ($newEndsAt ?? $locked->ends_at)->isPast();
        $replayAfter = $replay === null ? $locked->replay_url : $replay[0];
        $notesAfter = $notes === null ? $locked->notes : $notes[0];

        if ($newEndsAt !== null) {
            if ($published && $locked->starts_at->isFuture() && $over) {
                throw new BusinessException(self::NOT_HAPPENED_MESSAGE);
            }
            // In every state: a cancelled event moved to a later time and published again would hold a replay before it
            // happens
            if ($locked->isPast() && ! $over && ($replayAfter !== null || $notesAfter !== '')) {
                throw new BusinessException(self::MOVE_WITH_REPLAY_MESSAGE);
            }
        }

        $tooEarly = [];
        if ($replay !== null && $replay[0] !== null && ! ($published && $over)) {
            $tooEarly['replay_url'] = self::REPLAY_TOO_EARLY_MESSAGE;
        }
        if ($notes !== null && $notes[0] !== '' && ! ($published && $over)) {
            $tooEarly['notes'] = self::NOTES_TOO_EARLY_MESSAGE;
        }
        if ($tooEarly !== []) {
            throw ValidationException::withMessages($tooEarly);
        }
    }
}
