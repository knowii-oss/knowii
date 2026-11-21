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
     * Whether the account used its budget of this minute
     */
    public static function isUsedUpBy(User $user): bool
    {
        return RateLimiter::tooManyAttempts(self::key($user), Constants::EMAIL_CHANGES_PER_MINUTE);
    }

    /**
     * Counts one request of the account that sends a mail
     */
    public static function count(User $user): void
    {
        RateLimiter::hit(self::key($user), 60);
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
