<?php

namespace App\Support;

/**
 * The words of a refusal after too many tries, with the time to wait: "a minute", a number of minutes up to 90, else a
 * number of hours
 */
final class TooManyTries
{
    public static function words(int $seconds): string
    {
        $minutes = (int) ceil(max(1, $seconds) / 60);
        if ($minutes <= 1) {
            return __('Too many tries. Please wait a minute, then try again.');
        }
        if ($minutes <= 90) {
            return __('Too many tries. Please wait :minutes minutes, then try again.', ['minutes' => $minutes]);
        }

        return __('Too many tries. Please wait :hours hours, then try again.', ['hours' => (int) ceil($minutes / 60)]);
    }
}
