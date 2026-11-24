<?php

namespace App\Support;

use App\Constants;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One budget for every request that sends a mail of a change of the email address (#1706, ADR 0058):
 * Constants::EMAIL_CHANGES_PER_MINUTE a minute per account. The routes of the link, of "Send the link again" and of "Cancel
 * the change" (web and API) count through the limiter `email-changes` (the middleware throttle:email-changes); the profile
 * form counts each change of the address, and each correction of an address that was never verified, on the same key.
 * The profile form also counts them for the day: Constants::EMAIL_CHANGES_PER_DAY a day per account, each to a new
 * address.
 */
final class EmailChangeBudget
{
    public const string LIMITER = 'email-changes';

    /**
     * The limit of the limiter `email-changes`: per account, or per IP address for a request without one
     */
    public static function limit(Request $request): Limit
    {
        $user = $request->user();

        return Limit::perMinute(Constants::EMAIL_CHANGES_PER_MINUTE)->by(self::LIMITER.':'.($user instanceof User ? self::accountKey($user) : 'ip:'.$request->ip()));
    }

    /**
     * The seconds until the account may change its address again in the profile form: 0 while its budget of the minute and
     * of the day has room
     */
    public static function waitFor(User $user): int
    {
        $wait = 0;
        if (RateLimiter::tooManyAttempts(self::key($user), Constants::EMAIL_CHANGES_PER_MINUTE)) {
            $wait = max(1, RateLimiter::availableIn(self::key($user)));
        }
        if (RateLimiter::tooManyAttempts(self::dayKey($user), Constants::EMAIL_CHANGES_PER_DAY)) {
            $wait = max($wait, 1, RateLimiter::availableIn(self::dayKey($user)));
        }

        return $wait;
    }

    /**
     * Counts one change of the address of the profile form, which sends a mail: for the minute and for the day
     */
    public static function count(User $user): void
    {
        RateLimiter::hit(self::key($user), 60);
        RateLimiter::hit(self::dayKey($user), 86400);
    }

    private static function dayKey(User $user): string
    {
        return self::LIMITER.':day:'.self::accountKey($user);
    }

    private static function accountKey(User $user): string
    {
        return 'user:'.$user->id;
    }

    /**
     * The key that the middleware throttle:email-changes counts for the account: the framework hashes the name of the
     * limiter with the key of its limit (Illuminate\Routing\Middleware\ThrottleRequests)
     */
    private static function key(User $user): string
    {
        return md5(self::LIMITER.self::LIMITER.':'.self::accountKey($user));
    }
}
