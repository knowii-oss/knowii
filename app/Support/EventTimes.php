<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

/**
 * The start and the end of an event (#1513, ADR 0053, design 4). The API takes each one as ISO 8601 with an explicit
 * offset ("2026-11-18T17:00:00+01:00"), with the IANA zone in which the host entered it. The server keeps the UTC
 * instant (the truth) and checks that the offset is the real offset of the zone at that instant: a wrong offset, a value
 * without an offset and a local time that does not exist in the zone (the hour skipped when the clocks go forward) are
 * field errors. A local time that exists twice (the hour when the clocks go back) is accepted with either real offset:
 * the offset decides the instant. PHP date classes and Carbon only.
 */
final class EventTimes
{
    public const string FORMAT_MESSAGE = 'Use a date and a time in ISO 8601 with an offset, like 2026-11-18T17:00:00+01:00';

    public const string OFFSET_MESSAGE = 'This offset is not the offset of the time zone at this time';

    /**
     * ISO 8601: a date, a time with or without seconds (and their fractions), and an offset (Z or ±hh:mm)
     */
    private const string PATTERN = '/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})(:\d{2}(?:\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})$/';

    /**
     * The UTC instant of a value of the API, checked against the zone. A field error on `$field` when it fails.
     */
    public static function parse(string $field, string $value, string $zone): CarbonImmutable
    {
        if (preg_match(self::PATTERN, $value, $parts) !== 1) {
            throw ValidationException::withMessages([$field => self::FORMAT_MESSAGE]);
        }

        $local = $parts[1].' '.$parts[2].':'.($parts[3] !== '' ? substr($parts[3], 1, 2) : '00');
        try {
            $given = new DateTimeImmutable($value);
        } catch (\Exception) {
            throw ValidationException::withMessages([$field => self::FORMAT_MESSAGE]);
        }
        // A date that does not exist in any calendar (31 November) is moved by PHP: refuse it
        if ($given->format('Y-m-d H:i:s') !== $local) {
            throw ValidationException::withMessages([$field => self::FORMAT_MESSAGE]);
        }

        $timeZone = new DateTimeZone($zone);
        $instant = CarbonImmutable::instance($given)->utc();
        // The offset is right when the instant, read in the zone, gives the same local time
        if ($instant->setTimezone($timeZone)->format('Y-m-d H:i:s') === $local) {
            return $instant;
        }

        $gap = self::gapAround($local, $timeZone);
        if ($gap !== null) {
            throw ValidationException::withMessages([$field => sprintf('This time does not exist in this zone. Choose %s or earlier, or %s or later.', $gap[0], $gap[1])]);
        }

        throw ValidationException::withMessages([$field => self::OFFSET_MESSAGE]);
    }

    /**
     * The same wall-clock time, a number of days later, in the zone: 17:00 in Brussels stays 17:00 in Brussels over a
     * change of summer time (the instant moves by one hour). A wall-clock time that the zone skips that day is a field
     * error on `$field`, with the two hours around the gap, as for a value of the API. A time that exists twice takes
     * the offset that it had before.
     */
    public static function daysLaterInZone(string $field, CarbonImmutable $instant, string $zone, int $days): CarbonImmutable
    {
        $before = $instant->setTimezone($zone);
        // Wall-clock arithmetic: the local time read as UTC, so that no change of the clocks moves it
        $local = CarbonImmutable::parse($before->format('Y-m-d H:i:s'), 'UTC')->addDays($days)->format('Y-m-d H:i:s');
        $timeZone = new DateTimeZone($zone);

        foreach (array_unique([$before->getOffset(), ...self::offsetsAround($local, $timeZone)]) as $offset) {
            $candidate = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $local, 'UTC')?->subSeconds($offset);
            if ($candidate !== null && $candidate->setTimezone($timeZone)->format('Y-m-d H:i:s') === $local) {
                return $candidate;
            }
        }

        $gap = self::gapAround($local, $timeZone);
        throw ValidationException::withMessages([$field => $gap === null
            ? self::OFFSET_MESSAGE
            : sprintf('This time does not exist in this zone. Choose %s or earlier, or %s or later.', $gap[0], $gap[1])]);
    }

    /**
     * The offsets of the zone in the two days around a local time
     *
     * @return list<int>
     */
    private static function offsetsAround(string $local, DateTimeZone $zone): array
    {
        $wall = (new DateTimeImmutable($local, new DateTimeZone('UTC')))->getTimestamp();

        return array_values(array_unique(array_map(static fn (array $transition): int => $transition['offset'], $zone->getTransitions($wall - 2 * 86400, $wall + 2 * 86400))));
    }

    /**
     * The last minute before and the first minute after the hour that the zone skips, when the local time falls in it
     *
     * @return array{0: string, 1: string}|null as "01:59" and "03:00"
     */
    private static function gapAround(string $local, DateTimeZone $zone): ?array
    {
        // The local time as seconds, read as if it were UTC: the transitions compare to it with their offsets
        $wall = (new DateTimeImmutable($local, new DateTimeZone('UTC')))->getTimestamp();
        $transitions = $zone->getTransitions($wall - 2 * 86400, $wall + 2 * 86400);

        for ($index = 1, $count = count($transitions); $index < $count; $index++) {
            $before = $transitions[$index - 1]['offset'];
            $after = $transitions[$index]['offset'];
            $at = $transitions[$index]['ts'];
            if ($after > $before && $wall >= $at + $before && $wall < $at + $after) {
                return [gmdate('H:i', $at + $before - 60), gmdate('H:i', $at + $after)];
            }
        }

        return null;
    }
}
