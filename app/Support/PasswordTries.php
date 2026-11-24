<?php

namespace App\Support;

use App\Constants;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * The tries of the current password. Every place that asks for it checks it here: the confirmation of the password, the
 * change of the password, "Log out other browser sessions", the deletion of the account (the page and the API), a change of
 * the email address in the profile form, and the transfer of the ownership of a community.
 *
 * A wrong password counts for what signed the request in (App\Support\RequestCredential), in one of three counts:
 *
 * 1. A session that a sign-in made (the form, the API sign-in, a sign-in link, the provider, the second factor) has its own
 *    count.
 * 2. The sessions that the "Remember me" cookie made share one count per account: the cookie signs in without a password.
 * 3. The API tokens of an account share one count per account: the API tokens page makes a token without the password.
 *
 * So a count starts again only with a new proof of the person (ADR 0067).
 *
 * Never one count for the account as a whole, so the tries of the tokens, or of the sessions of the cookie, change nothing
 * for a session that signed in. The limit of each count is Constants::PASSWORD_TRIES wrong passwords in
 * Constants::PASSWORD_TRIES_MINUTES minutes, for all these places together. Only a wrong password counts, and a right one
 * gives no earlier try back.
 *
 * A try is counted before the password is checked, in one step of the cache: tries that run at the same time cannot pass
 * the limit together. So never call it inside a database transaction that can be rolled back: the count could go with it.
 *
 * A session that reaches the limit of its count ends (App\Support\EndedSession): the sign-in page says "Too many wrong
 * passwords. Sign in again.", and a request that expects JSON gets a 401 with these words, because the session is gone:
 * waiting changes nothing, a new sign-in does. The "Remember me" token of the account is written again with it, so no
 * "Remember me" cookie of before signs in: the next sign-in of each browser needs the password. The tokens of an account
 * that reached the limit are refused on these places until the 15 minutes of the first try are over (429, the JSON error of
 * ADR 0011, with `Retry-After`); none is deleted.
 */
final class PasswordTries
{
    /**
     * What the sign-in page says after the last try of a session
     */
    public const string SIGN_IN_AGAIN = 'Too many wrong passwords. Sign in again.';

    /**
     * Checks the current password of the account for the credential of the request: true for the right password
     *
     * @param  Closure(): bool  $isRight  The check itself: it runs only while the count of the credential has tries left
     *
     * @throws AuthenticationException When the count of the session has no tries left: the session is ended
     * @throws ThrottleRequestsException When the tokens of the account used up their tries
     */
    public static function check(User $user, Closure $isRight, ?Request $request = null): bool
    {
        $request ??= request();
        $key = self::keyOf($request, $user);
        $seconds = Constants::PASSWORD_TRIES_MINUTES * 60;

        // Counted first: this try has its place before the password is read
        $tries = RateLimiter::increment($key, $seconds);
        if ($tries > Constants::PASSWORD_TRIES) {
            throw self::refusal($request, $user, $key);
        }

        if ($isRight()) {
            // Only a wrong password counts: this try gives its own place back, never the place of an earlier one
            RateLimiter::decrement($key, $seconds);

            return true;
        }

        // The last wrong try of the count of a session ends that session at once
        if ($tries === Constants::PASSWORD_TRIES && RequestCredential::isSession($request, $user)) {
            throw self::refusal($request, $user, $key);
        }

        return false;
    }

    /**
     * The key of the count of the request: one for all the API tokens of the account, one for all the sessions of the
     * account that the "Remember me" cookie made, and one for each session that a sign-in made
     */
    private static function keyOf(Request $request, User $user): string
    {
        if (RequestCredential::isToken($user)) {
            return 'password-tries:tokens:'.$user->cuid;
        }
        if (RequestCredential::isRecalledSession($request, $user)) {
            return 'password-tries:recalled:'.$user->cuid;
        }

        return 'password-tries:'.RequestCredential::keyOf($request, $user);
    }

    /**
     * The answer for a credential whose count has no tries left
     */
    private static function refusal(Request $request, User $user, string $key): AuthenticationException|ThrottleRequestsException
    {
        if (RequestCredential::isSession($request, $user)) {
            // No "Remember me" cookie of before signs in after it: each browser of the account needs the password again
            $rememberToken = Str::random(60);
            User::query()->whereKey($user->getKey())->update(['remember_token' => $rememberToken]);
            $user->forceFill(['remember_token' => $rememberToken])->syncOriginalAttribute('remember_token');

            return EndedSession::end($request, self::SIGN_IN_AGAIN);
        }

        $seconds = max(1, RateLimiter::availableIn($key));

        return new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => (string) $seconds]);
    }
}
