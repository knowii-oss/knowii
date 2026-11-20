<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A whole number as a request gives it (#1515): a JSON number without a fraction, or a text of digits. Put it before
 * `integer`, which also takes the boolean `true` and then stores 1.
 */
final readonly class WholeNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            $fail('validation.integer')->translate();
        }
    }
}
