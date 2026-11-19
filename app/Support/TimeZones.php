<?php

namespace App\Support;

use DateTimeZone;
use IntlTimeZone;

/**
 * The time zone of an account (#1476, ADR 0048) or of an event: an IANA name that PHP knows, set only by a request of the
 * member. One zone has one name in the database, its current one: a browser can still give an older name of a zone
 * (`Asia/Calcutta` for `Asia/Kolkata`, `Europe/Kiev` for `Europe/Kyiv`), which is accepted and stored under the current
 * name (#1678).
 */
final class TimeZones
{
    /**
     * @return list<string>
     */
    public static function identifiers(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    /**
     * The current name of a zone: the name itself when it is one of `identifiers()`, else the current name of an older
     * name that the time zone data still knows. Null for a name that is not a zone
     */
    public static function current(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $identifiers = self::identifiers();
        if (in_array($name, $identifiers, true)) {
            return $name;
        }

        // ICU knows the older names and is strict about the spelling ("europe/brussels" and "+02:00" give false)
        $current = IntlTimeZone::getIanaID($name);

        return is_string($current) && in_array($current, $identifiers, true) ? $current : null;
    }

    public static function isValid(?string $name): bool
    {
        return self::current($name) !== null;
    }

    /**
     * The current name of the zone when it is valid, else null (the sign-up never fails because of a zone)
     */
    public static function orNull(?string $name): ?string
    {
        return self::current($name);
    }
}
