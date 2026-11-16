<?php

namespace App\Support;

use DateTimeZone;

/**
 * The time zone of an account (#1476, ADR 0048): an IANA name that PHP knows, set only by a request of the member.
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

    public static function isValid(?string $name): bool
    {
        return $name !== null && in_array($name, self::identifiers(), true);
    }

    /**
     * The name when it is valid, else null (the sign-up never fails because of a zone)
     */
    public static function orNull(?string $name): ?string
    {
        return self::isValid($name) ? $name : null;
    }
}
