<?php

namespace App\Rules;

use App\Support\TimeZones;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A time zone that the platform knows (#1476, #1678): a current IANA name, or an older name of a zone that a browser can
 * still give (`Asia/Calcutta`). The action stores the current name (`TimeZones::current`).
 */
final readonly class KnownTimeZone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! TimeZones::isValid($value)) {
            $fail('Choose a time zone of the list, like Europe/Brussels');
        }
    }
}
