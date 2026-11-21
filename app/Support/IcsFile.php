<?php

namespace App\Support;

use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;

/**
 * The calendar file of one event (#1515, design 6.5, ADR 0053 "The calendar file and My events"): an iCalendar object
 * (RFC 5545) with one VEVENT, written without a library. It holds no state and asks no question: the caller gives the
 * values that this reader may get (the meeting address and the place only when EventPolicy::viewLocation passes), and it
 * returns the bytes of the file. It knows nothing of a request, a user or a policy. The mails of slice E5 attach the same
 * file.
 *
 * The lines, in this order, each one time: BEGIN:VCALENDAR, VERSION, PRODID, BEGIN:VEVENT, UID, DTSTAMP, DTSTART, DTEND,
 * SUMMARY, DESCRIPTION, LOCATION (only with a value), URL, SEQUENCE, STATUS, END:VEVENT, END:VCALENDAR. No METHOD (the
 * answer stays in Knowii, RFC 5545 3.7.2), no VTIMEZONE (the times are UTC instants, 3.3.5), no VALARM (the calendar of
 * the member decides), no ORGANIZER and no ATTENDEE (3.8.4.3: the file never names a member), no LAST-MODIFIED (without
 * METHOD it equals DTSTAMP, 3.8.7.2).
 *
 * The text values (SUMMARY, DESCRIPTION, LOCATION) are escaped (3.3.11), so that a title can never add a line or a
 * property. Each line ends with CRLF and is at most 75 octets; a longer line is folded with CRLF and one space, never
 * inside a UTF-8 character (3.1).
 */
final class IcsFile
{
    public const string CONTENT_TYPE = 'text/calendar; charset=utf-8';

    public const string PRODID = '-//Knowii//Events//EN';

    // The plain text of the description of the event: its first characters
    public const int DESCRIPTION_LENGTH = 300;

    // [W] The words of the file (#1515): they wait for the owner
    public const string PAGE_LABEL = 'Event page: ';

    public const string MEETING_LABEL = 'Meeting: ';

    public const string CANCELLED_PREFIX = 'Cancelled: ';

    // RFC 5545 3.1: a line of at most 75 octets, without its line break
    private const int LINE_OCTETS = 75;

    private const string CRLF = "\r\n";

    private function __construct() {}

    /**
     * The file of one event
     *
     * @param  string  $uid  The cuid of the event: stable for the life of the event, and it names nobody (RFC 7986 5.3)
     * @param  DateTimeInterface  $stamp  The last revision of what this file holds (DTSTAMP): never the time of the request
     * @param  string  $description  The description of the event, in Markdown: its first 300 characters in plain text
     * @param  string  $pageUrl  The absolute address of the page of the event (URL, and the line "Event page:")
     * @param  ?string  $meetingUrl  The meeting address, only when this reader may see it
     * @param  ?string  $place  The place, only when this reader may see it
     */
    public static function event(
        string $uid,
        DateTimeInterface $stamp,
        DateTimeInterface $start,
        DateTimeInterface $end,
        string $title,
        string $description,
        string $pageUrl,
        ?string $meetingUrl,
        ?string $place,
        int $sequence,
        bool $cancelled,
    ): string {
        $meetingUrl = $meetingUrl === '' ? null : $meetingUrl;
        $place = $place === '' ? null : $place;

        $excerpt = Excerpt::of($description, self::DESCRIPTION_LENGTH);
        $details = [
            ...($excerpt === '' ? [] : [$excerpt, '']),
            self::PAGE_LABEL.$pageUrl,
            ...($meetingUrl === null ? [] : [self::MEETING_LABEL.$meetingUrl]),
        ];
        // The place, else the meeting address. With both, the meeting address is in the description
        $location = $place ?? $meetingUrl;

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:'.self::PRODID,
            'BEGIN:VEVENT',
            'UID:'.self::token($uid),
            'DTSTAMP:'.self::utc($stamp),
            'DTSTART:'.self::utc($start),
            'DTEND:'.self::utc($end),
            'SUMMARY:'.self::text(($cancelled ? self::CANCELLED_PREFIX : '').$title),
            'DESCRIPTION:'.self::text(implode("\n", $details)),
            ...($location === null ? [] : ['LOCATION:'.self::text($location)]),
            'URL:'.self::uri($pageUrl),
            'SEQUENCE:'.max(0, $sequence),
            'STATUS:'.($cancelled ? 'CANCELLED' : 'CONFIRMED'),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode('', array_map(self::fold(...), $lines));
    }

    /**
     * A TEXT value (RFC 5545 3.3.11), in this order: a backslash, a semicolon and a comma get a backslash before them,
     * each line break (CRLF, CR, LF) becomes the two characters "\n", a tab becomes a space, and every other control
     * character goes. A colon stays.
     */
    public static function text(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');
        $value = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $value);
        $value = (string) preg_replace("/\r\n|\r|\n/", '\\n', $value);
        $value = str_replace("\t", ' ', $value);

        return (string) preg_replace('/[\x00-\x1F\x7F]/', '', $value);
    }

    /**
     * A content line with its line break, folded at 75 octets (RFC 5545 3.1): each next line starts with one space, which
     * counts in its 75 octets. The cut is always between two characters, so each line is valid UTF-8 alone.
     */
    public static function fold(string $line): string
    {
        $folded = '';
        $current = '';
        $limit = self::LINE_OCTETS;
        foreach (mb_str_split($line, 1, 'UTF-8') as $character) {
            if (strlen($current) + strlen($character) > $limit) {
                $folded .= $current.self::CRLF;
                $current = ' ';
                $limit = self::LINE_OCTETS;
            }
            $current .= $character;
        }

        return $folded.$current.self::CRLF;
    }

    /**
     * A UTC DATE-TIME (RFC 5545 3.3.5): 20261118T160000Z
     */
    private static function utc(DateTimeInterface $time): string
    {
        return Carbon::instance($time)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /**
     * The UID as an opaque token (RFC 7986 5.3): letters, digits and "-" only, under 255 octets
     */
    private static function token(string $value): string
    {
        return substr((string) preg_replace('/[^A-Za-z0-9-]/', '', $value), 0, 254);
    }

    /**
     * A URI value (RFC 5545 3.8.4.6): no escape of TEXT. The application builds it from its own route; a control
     * character could only end the line, so none stays
     */
    private static function uri(string $value): string
    {
        return (string) preg_replace('/[\x00-\x20\x7F]/', '', $value);
    }
}
