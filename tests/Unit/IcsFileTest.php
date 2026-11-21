<?php

use App\Support\IcsFile;
use Carbon\CarbonImmutable;

/*
 * The calendar file of one event (#1515, ADR 0053, "The calendar file and My events"): the class alone, byte for byte.
 * The instant of the journeys of the design (section 5): 18 November 2026, 16:00 to 17:00 UTC.
 */

const ICS_UID = 'evx5k2m9q8r7t6w4z3y1abcd';

const ICS_PAGE = 'https://knowii.example.com/community/example-community/space/office-hours/event/weekly-review-office-hour';

const ICS_MEETING = 'https://meet.example.com/abc-defg-hij';

const ICS_PLACE = 'Room 2, Main street 1, Brussels';

/**
 * The file of the fixed event, with the changes of a test
 *
 * @param  array<string, mixed>  $changes
 */
function icsFile(array $changes = []): string
{
    $values = [
        'uid' => ICS_UID,
        'stamp' => CarbonImmutable::parse('2026-11-17T10:30:00Z'),
        // In the zone of the host: the file holds the UTC instant
        'start' => CarbonImmutable::parse('2026-11-18T17:00:00+01:00'),
        'end' => CarbonImmutable::parse('2026-11-18T18:00:00+01:00'),
        'title' => 'Weekly review office hour',
        'description' => "Bring your **questions** about the weekly review.\n\nWe read the notes of the week together, one by one.",
        'pageUrl' => ICS_PAGE,
        'meetingUrl' => ICS_MEETING,
        'place' => ICS_PLACE,
        'sequence' => 3,
        'cancelled' => false,
        ...$changes,
    ];

    return IcsFile::event(...$values);
}

/**
 * The content lines of a file: each CRLF that a space follows is removed (RFC 5545 3.1), then the file is cut at CRLF
 *
 * @return list<string>
 */
function icsUnfold(string $file): array
{
    $lines = explode("\r\n", str_replace("\r\n ", '', $file));
    // The last line ends with CRLF too
    expect(array_pop($lines))->toBe('');

    return $lines;
}

/**
 * The value of one property of a file, after the unfold
 */
function icsValue(string $file, string $name): ?string
{
    foreach (icsUnfold($file) as $line) {
        if (str_starts_with($line, $name.':')) {
            return substr($line, strlen($name) + 1);
        }
    }

    return null;
}

test('the file of an event, byte for byte', function () {
    expect(icsFile())->toBe(
        "BEGIN:VCALENDAR\r\n"
        ."VERSION:2.0\r\n"
        ."PRODID:-//Knowii//Events//EN\r\n"
        ."BEGIN:VEVENT\r\n"
        ."UID:evx5k2m9q8r7t6w4z3y1abcd\r\n"
        ."DTSTAMP:20261117T103000Z\r\n"
        ."DTSTART:20261118T160000Z\r\n"
        ."DTEND:20261118T170000Z\r\n"
        ."SUMMARY:Weekly review office hour\r\n"
        ."DESCRIPTION:Bring your questions about the weekly review. We read the notes\r\n"
        ."  of the week together\\, one by one.\\n\\nEvent page: https://knowii.example.\r\n"
        ." com/community/example-community/space/office-hours/event/weekly-review-off\r\n"
        ." ice-hour\\nMeeting: https://meet.example.com/abc-defg-hij\r\n"
        ."LOCATION:Room 2\\, Main street 1\\, Brussels\r\n"
        ."URL:https://knowii.example.com/community/example-community/space/office-hou\r\n"
        ." rs/event/weekly-review-office-hour\r\n"
        ."SEQUENCE:3\r\n"
        ."STATUS:CONFIRMED\r\n"
        ."END:VEVENT\r\n"
        ."END:VCALENDAR\r\n"
    );
});

test('the file of a reader who may not see the meeting address and the place', function () {
    expect(icsFile(['meetingUrl' => null, 'place' => null]))->toBe(
        "BEGIN:VCALENDAR\r\n"
        ."VERSION:2.0\r\n"
        ."PRODID:-//Knowii//Events//EN\r\n"
        ."BEGIN:VEVENT\r\n"
        ."UID:evx5k2m9q8r7t6w4z3y1abcd\r\n"
        ."DTSTAMP:20261117T103000Z\r\n"
        ."DTSTART:20261118T160000Z\r\n"
        ."DTEND:20261118T170000Z\r\n"
        ."SUMMARY:Weekly review office hour\r\n"
        ."DESCRIPTION:Bring your questions about the weekly review. We read the notes\r\n"
        ."  of the week together\\, one by one.\\n\\nEvent page: https://knowii.example.\r\n"
        ." com/community/example-community/space/office-hours/event/weekly-review-off\r\n"
        ." ice-hour\r\n"
        ."URL:https://knowii.example.com/community/example-community/space/office-hou\r\n"
        ." rs/event/weekly-review-office-hour\r\n"
        ."SEQUENCE:3\r\n"
        ."STATUS:CONFIRMED\r\n"
        ."END:VEVENT\r\n"
        ."END:VCALENDAR\r\n"
    );
});

test('the file of an event without a description: the address of the page alone, and the meeting address as the place', function () {
    expect(icsFile(['description' => '', 'place' => null]))->toBe(
        "BEGIN:VCALENDAR\r\n"
        ."VERSION:2.0\r\n"
        ."PRODID:-//Knowii//Events//EN\r\n"
        ."BEGIN:VEVENT\r\n"
        ."UID:evx5k2m9q8r7t6w4z3y1abcd\r\n"
        ."DTSTAMP:20261117T103000Z\r\n"
        ."DTSTART:20261118T160000Z\r\n"
        ."DTEND:20261118T170000Z\r\n"
        ."SUMMARY:Weekly review office hour\r\n"
        ."DESCRIPTION:Event page: https://knowii.example.com/community/example-commun\r\n"
        ." ity/space/office-hours/event/weekly-review-office-hour\\nMeeting: https://m\r\n"
        ." eet.example.com/abc-defg-hij\r\n"
        ."LOCATION:https://meet.example.com/abc-defg-hij\r\n"
        ."URL:https://knowii.example.com/community/example-community/space/office-hou\r\n"
        ." rs/event/weekly-review-office-hour\r\n"
        ."SEQUENCE:3\r\n"
        ."STATUS:CONFIRMED\r\n"
        ."END:VEVENT\r\n"
        ."END:VCALENDAR\r\n"
    );
});

test('the file of a cancelled event says it in its status and in its summary', function () {
    expect(icsFile(['cancelled' => true, 'sequence' => 4, 'meetingUrl' => null, 'place' => null, 'description' => '']))->toBe(
        "BEGIN:VCALENDAR\r\n"
        ."VERSION:2.0\r\n"
        ."PRODID:-//Knowii//Events//EN\r\n"
        ."BEGIN:VEVENT\r\n"
        ."UID:evx5k2m9q8r7t6w4z3y1abcd\r\n"
        ."DTSTAMP:20261117T103000Z\r\n"
        ."DTSTART:20261118T160000Z\r\n"
        ."DTEND:20261118T170000Z\r\n"
        ."SUMMARY:Cancelled: Weekly review office hour\r\n"
        ."DESCRIPTION:Event page: https://knowii.example.com/community/example-commun\r\n"
        ." ity/space/office-hours/event/weekly-review-office-hour\r\n"
        ."URL:https://knowii.example.com/community/example-community/space/office-hou\r\n"
        ." rs/event/weekly-review-office-hour\r\n"
        ."SEQUENCE:4\r\n"
        ."STATUS:CANCELLED\r\n"
        ."END:VEVENT\r\n"
        ."END:VCALENDAR\r\n"
    );
});

test('a text value is escaped: backslash, semicolon, comma and line breaks, a tab becomes a space, other control characters go, a colon stays', function () {
    $raw = "a\\b;c,d:e\r\nf\ng\rh\ti\x07j\x7Fk";
    $escaped = 'a\\\\b\\;c\\,d:e\\nf\\ng\\nh ijk';
    $file = icsFile(['title' => $raw, 'place' => $raw, 'meetingUrl' => null, 'description' => '']);

    expect(icsValue($file, 'SUMMARY'))->toBe($escaped)
        ->and(icsValue($file, 'LOCATION'))->toBe($escaped)
        ->and(IcsFile::text($raw))->toBe($escaped)
        // The description: its plain text is one line, then the line break before the address of the page
        ->and(icsValue(icsFile(['description' => 'Notes; a, b\\c: d', 'meetingUrl' => null]), 'DESCRIPTION'))->toBe('Notes\\; a\\, b\\\\c: d\\n\\nEvent page: '.ICS_PAGE)
        // A meeting address is a text in the description and in the place: its comma and its semicolon are escaped
        ->and(icsValue(icsFile(['meetingUrl' => 'https://meet.example.com/a,b;c', 'place' => null, 'description' => '']), 'LOCATION'))->toBe('https://meet.example.com/a\\,b\\;c');
});

test('a title can never add a line or a property', function () {
    $file = icsFile(['title' => "Review, part; two\r\nATTENDEE:mailto:someone@example.com", 'place' => null, 'meetingUrl' => null]);
    $lines = icsUnfold($file);

    expect($lines)->toHaveCount(15)
        ->and(array_map(fn (string $line) => explode(':', $line, 2)[0], $lines))->toBe([
            'BEGIN', 'VERSION', 'PRODID', 'BEGIN', 'UID', 'DTSTAMP', 'DTSTART', 'DTEND', 'SUMMARY', 'DESCRIPTION', 'URL', 'SEQUENCE', 'STATUS', 'END', 'END',
        ])
        ->and(icsValue($file, 'SUMMARY'))->toBe('Review\\, part\\; two\\nATTENDEE:mailto:someone@example.com')
        ->and($file)->not->toContain("\r\nATTENDEE");
});

test('each line is at most 75 octets, valid UTF-8 alone and ends with CRLF, and the unfold gives the content lines', function () {
    // Letters of 1, 2, 3 and 4 octets, 200 characters
    $title = mb_substr(str_repeat('aé€😀', 50), 0, 200);
    $file = icsFile(['title' => $title]);
    $physical = explode("\r\n", $file);

    expect(array_pop($physical))->toBe('')
        ->and(str_starts_with($file, "\xEF\xBB\xBF"))->toBeFalse()
        // No line break other than CRLF
        ->and(substr_count($file, "\n"))->toBe(substr_count($file, "\r\n"))
        ->and(substr_count($file, "\r"))->toBe(substr_count($file, "\r\n"));
    foreach ($physical as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75)
            ->and(mb_check_encoding($line, 'UTF-8'))->toBeTrue();
    }
    expect(icsValue($file, 'SUMMARY'))->toBe($title)
        ->and(icsUnfold($file))->toHaveCount(16);
});

test('a long description gives its first 300 characters, and the address of the page stays whole', function () {
    $file = icsFile(['description' => str_repeat('word ', 400), 'meetingUrl' => null]);
    [$excerpt, $rest] = explode('\\n\\n', (string) icsValue($file, 'DESCRIPTION'), 2);

    expect(mb_strlen($excerpt))->toBe(300)
        ->and(str_ends_with($excerpt, '…'))->toBeTrue()
        ->and($rest)->toBe('Event page: '.ICS_PAGE);
});

test('the file holds no line that the design leaves out', function () {
    $names = array_map(fn (string $line) => explode(':', $line, 2)[0], icsUnfold(icsFile()));

    expect($names)->not->toContain('METHOD')->not->toContain('VTIMEZONE')->not->toContain('VALARM')
        ->not->toContain('ORGANIZER')->not->toContain('ATTENDEE')->not->toContain('LAST-MODIFIED')
        ->not->toContain('CREATED')->not->toContain('CATEGORIES')
        ->and(array_filter($names, fn (string $name) => str_starts_with($name, 'X-')))->toBe([])
        ->and(icsFile())->not->toContain('TZID');
});
