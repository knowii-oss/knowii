<?php

namespace App\Http\Controllers\API;

use App\Actions\Events\EventPublication;
use App\Enums\KnowiiEventState;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Event;
use App\Models\Space;
use App\Models\User;
use App\Support\IcsFile;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The calendar file of an event (#1515, design 6.5, ADR 0053 "The calendar file and My events"): one iCalendar file per
 * event, for a member, built at each request by `IcsFile`. A controller of its own: the only route of an event that
 * answers a file and not JSON, with its own limiter, and the place that decides what each reader gets in the file.
 *
 * It writes no row, file or job of its own and no log line, and nothing records which file a member asked for. What the
 * middleware write on every route of the API still happens: the counter of the limiter, `last_used_at` of a token, the
 * session of a browser.
 */
class EventCalendarFileApiController extends Controller
{
    public const string NOT_A_MEMBER_MESSAGE = 'Only the members of the community get the calendar file of an event';

    public const string DRAFT_MESSAGE = 'This event is a draft. It has no calendar file.';

    /**
     * The calendar file of an event (`text/calendar`, RFC 5545), as an attachment: one VEVENT with the start and the end
     * in UTC, the title, the first 300 characters of the description, the address of the page, the sequence (a moved
     * event gives the same UID and a higher SEQUENCE) and the status (CONFIRMED, or CANCELLED for a cancelled event).
     * The meeting address and the place are in it only when you may see them, as in `GET .../events/{event}`. For the
     * members of the community who can see the event: 401 without an account; a space that you cannot read answers first
     * (`SpaceAccess`: 404 for a person who is not a member, 403 for a member); then 404 for an event that you may not see
     * (a draft of another host), 403 for a person who is not a member, 422 for a draft. A refusal is the JSON error
     * of the API. The file names no member, and nothing records the download. A browser gets it only through the link of a
     * page, which sends the Referer that the session needs: a typed address gets 401. A calendar application cannot
     * subscribe to it: it needs your session or a token with events:read.
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws BusinessException
     */
    #[OpenApiResponse(200, 'The calendar file of the event, as an attachment (Content-Disposition: attachment; filename="<slug>-<date>.ics")', mediaType: IcsFile::CONTENT_TYPE, type: 'string', examples: ["BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Knowii//Events//EN\r\nBEGIN:VEVENT\r\nUID:...\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"])]
    #[OpenApiResponse(422, 'A draft has no calendar file: "This event is a draft. It has no calendar file."', type: 'array{message: string}')]
    #[OpenApiResponse(429, 'More than 30 calendar files in a minute', type: 'array{message: string}')]
    final public function show(Request $request, Community $community, Space $space, Event $event): Response
    {
        $user = $request->user() ?? throw new AuthenticationException;
        // The policies read the space and the community: they are known
        $space->setRelation('community', $community);
        $event->setRelation('space', $space);
        $event->setRelation('community', $community);

        EventPublication::assertVisible($user, $event);
        if (! $user->belongsToCommunity($community)) {
            throw new AuthorizationException(self::NOT_A_MEMBER_MESSAGE);
        }
        // A draft is not told to the members and its time is not final: no file, also for its host
        if ($event->state === KnowiiEventState::Draft) {
            throw new BusinessException(self::DRAFT_MESSAGE);
        }

        // The one check of the meeting address and the place, as the resource of the event asks it
        $seesLocation = Gate::forUser($user)->check('viewLocation', $event);

        $file = IcsFile::event(
            uid: $event->cuid,
            stamp: self::stamp($user, $event),
            start: $event->starts_at,
            end: $event->ends_at,
            title: $event->title,
            description: $event->body,
            pageUrl: route('events.show', ['communitySlug' => $community->slug, 'spaceSlug' => $space->slug, 'eventSlug' => $event->slug]),
            meetingUrl: $seesLocation ? $event->meeting_url : null,
            place: $seesLocation ? $event->place : null,
            sequence: $event->sequence,
            cancelled: $event->state === KnowiiEventState::Cancelled,
        );

        return response($file, 200, [
            'Content-Type' => IcsFile::CONTENT_TYPE,
            'Content-Disposition' => 'attachment; filename="'.self::fileName($event).'"',
            // Never shown inside the Knowii origin, and the browser must not guess another type
            'X-Content-Type-Options' => 'nosniff',
            // The file differs per reader: no shared cache keeps it
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * DTSTAMP: the last revision of what this file holds for this reader, never the time of the request, so that two
     * downloads of the same data give the same bytes. The event, and the answer of the reader: on a "going" event a Yes
     * gives the meeting address.
     */
    private static function stamp(User $user, Event $event): CarbonImmutable
    {
        $stamp = $event->updated_at->toImmutable();
        $answered = $event->answers()->where('user_id', $user->id)->value('updated_at');
        if (is_string($answered) || $answered instanceof DateTimeInterface) {
            $answeredAt = CarbonImmutable::parse($answered);
            if ($answeredAt->greaterThan($stamp)) {
                return $answeredAt;
            }
        }

        return $stamp;
    }

    /**
     * `<slug>-<date>.ics`: the slug of the event and the date of its start in the zone of the event, the same for every
     * reader. Lower-case letters, digits and "-" only, so it needs no `filename*` (RFC 6266)
     */
    private static function fileName(Event $event): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9-]+/', '', strtolower($event->slug)), '-');

        return ($slug === '' ? 'event' : $slug).'-'.$event->starts_at->toImmutable()->setTimezone($event->time_zone)->format('Y-m-d').'.ics';
    }
}
