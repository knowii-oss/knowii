<?php

namespace App\Support;

use App\Constants;
use App\Mail\SecondFactorLocked;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The tries of the code of two-factor authentication (a code of the authenticator app or a recovery code). Every place that
 * checks a code checks it here: the challenge of the web sign-in, the API sign-in, the deletion of the account (the page
 * and the API) and the transfer of the ownership of a community.
 *
 * The count is the account, whatever the IP address, the session or the token of the request: at most
 * Constants::SECOND_FACTOR_TRIES_PER_MINUTE wrong codes a minute AND Constants::SECOND_FACTOR_TRIES_PER_HOUR wrong codes an
 * hour, for all these places together. Then every code of the account is refused, also a right one, until the time of the
 * count is over. Only a wrong code counts: a right one does not count, and it gives no earlier wrong code back. The
 * caller checks the first factor before (the password, a sign-in link, the provider, a session), so a person who does not
 * have it cannot make the account wait.
 *
 * A code is counted before it is checked, in one step of the cache, as a try of the password (App\Support\PasswordTries):
 * codes that are sent at the same time cannot pass the limit together. A code that is refused is not a wrong code: it
 * leaves the count as it was.
 *
 * The wrong code that reaches the limit of the hour queues one mail to the member (App\Mail\SecondFactorLocked): the
 * second step is closed until the end of the hour, and a member who did not send the codes changes the password.
 */
final class SecondFactorTries
{
    /**
     * Checks a code of the account: true for a right code
     *
     * @param  Closure(): bool  $isRight  The check itself: it runs only while the count of the account has tries left
     *
     * @throws ThrottleRequestsException When the account has no try left: with the seconds to wait (`Retry-After`)
     */
    public static function check(User $user, Closure $isRight): bool
    {
        $hourKey = self::key($user, 'hour');
        $counts = [
            self::key($user, 'minute') => [Constants::SECOND_FACTOR_TRIES_PER_MINUTE, 60],
            $hourKey => [Constants::SECOND_FACTOR_TRIES_PER_HOUR, 3600],
        ];

        // Counted first: this code has its place before it is read
        $wait = 0;
        $hourCount = 0;
        foreach ($counts as $key => [$limit, $seconds]) {
            $count = RateLimiter::increment($key, $seconds);
            if ($key === $hourKey) {
                $hourCount = $count;
            }
            if ($count > $limit) {
                $wait = max($wait, RateLimiter::availableIn($key));
            }
        }

        if ($wait > 0 || ! $isRight()) {
            if ($wait > 0) {
                // A refused code is no wrong code: it gives its places back
                self::giveBack($counts);

                throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => (string) max(1, $wait)]);
            }

            // The wrong code that closes the hour: the member gets one mail
            if ($hourCount === self::lockCount()) {
                self::tellTheMember($user, $hourKey);
            }

            return false;
        }

        // A right code does not count: it gives its own places back, never the place of an earlier wrong code
        self::giveBack($counts);

        return true;
    }

    /**
     * The check of a form field that holds a code of the authenticator app or a recovery code, for an account with
     * two-factor authentication: a validation error on the field when the code is missing, wrong, or when the account has
     * no try left
     *
     * @param  (Closure(): void)|null  $onWrongCode  Runs for a wrong code, before the error
     *
     * @throws ValidationException
     */
    public static function validate(User $user, ?string $code, string $field = 'code', ?Closure $onWrongCode = null): void
    {
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return;
        }

        if ($code === null || $code === '') {
            throw ValidationException::withMessages([$field => __('Enter the code of your authenticator app or a recovery code.')]);
        }

        try {
            $right = self::check($user, static fn (): bool => SecondFactor::passesEither($user, $code));
        } catch (ThrottleRequestsException $e) {
            throw ValidationException::withMessages([$field => self::waitWords($e)]);
        }

        if (! $right) {
            if ($onWrongCode !== null) {
                $onWrongCode();
            }

            throw ValidationException::withMessages([$field => __('The provided two factor authentication code was invalid.')]);
        }
    }

    /**
     * The words of a refusal: "Too many tries", with the time to wait
     */
    public static function waitWords(ThrottleRequestsException $e): string
    {
        $retryAfter = $e->getHeaders()['Retry-After'] ?? '60';

        return TooManyTries::words(is_numeric($retryAfter) ? (int) $retryAfter : 60);
    }

    /**
     * The number of wrong codes in an hour that closes the second step of the account until the end of the hour
     */
    public static function lockCount(): int
    {
        return Constants::SECOND_FACTOR_TRIES_PER_HOUR;
    }

    /**
     * Queues the mail that tells the member that the second step is closed, and until when: at most one for each closed
     * hour. A mail that cannot be queued changes nothing of the answer: one log line with the class of the error
     */
    private static function tellTheMember(User $user, string $hourKey): void
    {
        $waitSeconds = max(1, RateLimiter::availableIn($hourKey));
        if (RateLimiter::increment('second-factor-locked-mail:'.$user->cuid, $waitSeconds) !== 1) {
            return;
        }

        Log::warning('Two-factor codes closed for the hour', ['user' => $user->cuid]);
        try {
            $until = CarbonImmutable::now()->addSeconds($waitSeconds);
            Mail::to($user)->queue((new SecondFactorLocked($until, $user->timeZone() ?? config()->string('app.timezone')))->recordDelivery($user));
        } catch (Throwable $exception) {
            Log::warning('The mail of closed two-factor codes was not queued', ['user' => $user->cuid, 'error' => $exception::class]);
        }
    }

    private static function key(User $user, string $period): string
    {
        return 'second-factor-tries:'.$period.':'.$user->cuid;
    }

    /**
     * @param  array<string, array{0: int, 1: int}>  $counts
     */
    private static function giveBack(array $counts): void
    {
        foreach ($counts as $key => [, $seconds]) {
            RateLimiter::decrement($key, $seconds);
        }
    }
}
