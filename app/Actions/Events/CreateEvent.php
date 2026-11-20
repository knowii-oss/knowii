<?php

namespace App\Actions\Events;

use App\Constants;
use App\Contracts\Events\CreatesEvents;
use App\Enums\KnowiiEventLocationVisibility;
use App\Enums\KnowiiEventState;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\Event;
use App\Models\Space;
use App\Models\Upload;
use App\Models\User;
use App\Rules\KnownTimeZone;
use App\Rules\MaxMarkdownImages;
use App\Rules\WholeNumber;
use App\Support\CommunityRoles;
use App\Support\EventTimes;
use App\Support\Markdown;
use App\Support\TimeZones;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Create an event in an events space (#1513, ADR 0053). The posting policy of the space decides who creates one. The
 * creator is the host unless they name another member, and then has no right of a host on the event.
 */
class CreateEvent implements CreatesEvents
{
    public const string HOST_MESSAGE = 'Choose a member of the community who can read this space';

    public const string COVER_MESSAGE = 'Choose an image uploaded in this community';

    final public function create(User $user, Space $space, array $input): Event
    {
        Log::info('Processing request to create an event', ['space' => $space->cuid]);

        Gate::forUser($user)->authorize('contribute', $space);

        if ($space->type !== KnowiiSpaceType::Events) {
            throw new BusinessException('Events can only be created in events spaces');
        }

        /** @var array{title: string, body?: string|null, starts_at: string, ends_at: string, time_zone: string, meeting_url?: string|null, place?: string|null, location_visibility?: string, cover_upload_cuid?: string|null, cover_alt?: string|null, capacity?: int|string|null, replay_expected?: bool|int|string, host_cuid?: string|null, state?: string, notify_now?: bool} $validated */
        $validated = Validator::make($input, [
            ...self::rules(true),
            'host_cuid' => ['sometimes', 'nullable', 'string'],
            'state' => ['sometimes', Rule::in([KnowiiEventState::Draft->value, KnowiiEventState::Published->value])],
            'notify_now' => ['sometimes', 'boolean'],
        ])->validate();

        // "Send now" skips the 5 minutes of the fan-out: the people who moderate the space only, as for a post
        $notifyNow = ($validated['notify_now'] ?? false) === true;
        if ($notifyNow) {
            Gate::forUser($user)->authorize('moderate', $space);
        }

        $community = $space->community;
        $times = self::times($user, $community, $validated, null);
        $hostCuid = $validated['host_cuid'] ?? null;
        $host = $hostCuid === null ? $user : self::host($space, $hostCuid);
        $coverId = self::coverId($community, $validated['cover_upload_cuid'] ?? null);
        $state = KnowiiEventState::from($validated['state'] ?? KnowiiEventState::Draft->value);
        $body = (string) ($validated['body'] ?? '');

        $event = DB::transaction(function () use ($space, $validated, $times, $host, $coverId, $state, $body) {
            $event = Event::create([
                'community_id' => $space->community_id,
                'space_id' => $space->id,
                'host_id' => $host->id,
                'title' => trim($validated['title']),
                'body' => $body,
                // Raw HTML escaped (ADR 0013), no mentions (like pages)
                'body_html' => $body === '' ? '' : Markdown::render($body),
                'starts_at' => $times['starts_at'],
                'ends_at' => $times['ends_at'],
                'time_zone' => $times['time_zone'],
                'meeting_url' => self::blankToNull($validated['meeting_url'] ?? null),
                'place' => self::blankToNull($validated['place'] ?? null),
                'location_visibility' => KnowiiEventLocationVisibility::from($validated['location_visibility'] ?? KnowiiEventLocationVisibility::Members->value),
                'cover_upload_id' => $coverId,
                'cover_alt' => self::blankToNull($validated['cover_alt'] ?? null),
                'capacity' => self::capacity($validated['capacity'] ?? null),
                'replay_expected' => filter_var($validated['replay_expected'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'state' => $state,
                'sequence' => 0,
                'published_notified_at' => null,
            ]);
            // The cover and the images of the description stay while the event shows them (#1201)
            $event->syncUploads();

            return $event;
        });

        Log::info('Event created', ['event' => $event->cuid, 'state' => $state->value]);
        // A published event tells the members and sends `space.event.created`; a draft sends nothing on the space channel
        if ($state === KnowiiEventState::Published) {
            EventPublication::published($user, $event, true, $notifyNow);
        }

        return $event;
    }

    /**
     * The rules of the fields that a create and an update share. The times are checked by times().
     *
     * @return array<string, list<string|object>>
     */
    public static function rules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'min:'.Constants::MIN_LENGTH_EVENT_TITLE, 'max:'.Constants::MAX_LENGTH_EVENT_TITLE],
            'body' => ['sometimes', 'nullable', 'string', 'max:'.Constants::MAX_LENGTH_EVENT_BODY, new MaxMarkdownImages],
            'starts_at' => [$presence, 'string'],
            'ends_at' => [$presence, 'string'],
            'time_zone' => [$presence, 'string', new KnownTimeZone],
            'meeting_url' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:'.Constants::MAX_LENGTH_EVENT_MEETING_URL],
            'place' => ['sometimes', 'nullable', 'string', 'max:'.Constants::MAX_LENGTH_EVENT_PLACE],
            'location_visibility' => ['sometimes', Rule::enum(KnowiiEventLocationVisibility::class)],
            'cover_upload_cuid' => ['sometimes', 'nullable', 'string'],
            'cover_alt' => ['sometimes', 'nullable', 'string', 'max:'.Constants::MAX_LENGTH_EVENT_COVER_ALT],
            // The limit of places (#1515): it counts the Yes answers. Null: no limit. A value under the number of Yes answers
            // is accepted, and nobody is removed. A number or a text of digits: `integer` alone takes `true` as 1
            'capacity' => ['sometimes', 'nullable', 'bail', new WholeNumber, 'integer', 'min:'.Constants::MIN_EVENT_CAPACITY, 'max:'.Constants::MAX_EVENT_CAPACITY],
            // A replay will exist: members may answer "Replay only"
            'replay_expected' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The start, the end and the zone after the change: the given values, checked against the zone, and the stored
     * values for the fields that are absent. The end is after the start and at most 24 hours later. A new start before
     * now is for the owner and the admins only (to enter past events).
     *
     * @param  array{starts_at?: string, ends_at?: string, time_zone?: string}  $validated
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, time_zone: string, start_changed: bool}
     */
    public static function times(User $user, Community $community, array $validated, ?Event $event): array
    {
        // An older name of a zone is stored under its current name (#1678)
        $zone = TimeZones::current($validated['time_zone'] ?? null) ?? $event->time_zone ?? 'UTC';
        $startsAt = isset($validated['starts_at']) ? EventTimes::parse('starts_at', $validated['starts_at'], $zone) : $event?->starts_at->toImmutable();
        $endsAt = isset($validated['ends_at']) ? EventTimes::parse('ends_at', $validated['ends_at'], $zone) : $event?->ends_at->toImmutable();
        if ($startsAt === null || $endsAt === null) {
            throw ValidationException::withMessages(['starts_at' => 'An event needs a start and an end']);
        }

        $startChanged = $event === null || ! $startsAt->equalTo($event->starts_at);
        self::checkTimes($user, $community, $startsAt, $endsAt, $startChanged);

        return ['starts_at' => $startsAt, 'ends_at' => $endsAt, 'time_zone' => $zone, 'start_changed' => $startChanged];
    }

    /**
     * The rules of a start and an end, for a create, an update and a duplicate: the end is after the start and at most
     * 24 hours later, and a new start before now is for the owner and the admins only.
     *
     * @throws ValidationException
     */
    public static function checkTimes(User $user, Community $community, CarbonImmutable $startsAt, CarbonImmutable $endsAt, bool $startChanged): void
    {
        if (! $endsAt->gt($startsAt)) {
            throw ValidationException::withMessages(['ends_at' => 'The end must be after the start']);
        }
        if ($startsAt->diffInSeconds($endsAt) > Constants::MAX_EVENT_DURATION_HOURS * 3600) {
            throw ValidationException::withMessages(['ends_at' => 'An event lasts '.Constants::MAX_EVENT_DURATION_HOURS.' hours at most']);
        }
        if ($startChanged && $startsAt->isPast() && ! CommunityRoles::isOwnerOrAdmin($user, $community)) {
            throw ValidationException::withMessages(['starts_at' => 'Choose a start in the future']);
        }
    }

    /**
     * The member named as host: a member of the community who can read the space. Anything else is a field error.
     */
    public static function host(Space $space, string $cuid): User
    {
        $host = User::query()->where('cuid', $cuid)->first();
        if ($host === null || ! $host->belongsToCommunity($space->community) || ! Gate::forUser($host)->check('view', $space)) {
            throw ValidationException::withMessages(['host_cuid' => self::HOST_MESSAGE]);
        }

        return $host;
    }

    /**
     * The upload of the cover: an upload of the same community (from `POST communities/{community}/uploads`). Null removes
     * the cover. The server never loads an image from another origin.
     */
    public static function coverId(Community $community, ?string $cuid): ?int
    {
        if ($cuid === null || trim($cuid) === '') {
            return null;
        }

        $upload = Upload::query()->where('cuid', $cuid)->where('community_id', $community->id)->first();
        if ($upload === null) {
            throw ValidationException::withMessages(['cover_upload_cuid' => self::COVER_MESSAGE]);
        }

        return $upload->id;
    }

    /**
     * The limit of places as a number, or null for no limit
     */
    public static function capacity(int|string|null $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public static function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
