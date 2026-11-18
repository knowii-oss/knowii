<?php

namespace App\Jobs\Middleware;

use App\Contracts\Mail\ChecksMailBudgets;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Log;
use Resend\Exceptions\ErrorException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * The hold of the notification mail (#1477, ADR 0054), the first middleware of every `NotificationMail`, before the rate
 * limiter.
 *
 * - While the mail budget of the month holds notification mail (`ChecksMailBudgets::isHeld()`), the job ends as done:
 *   nothing is sent and nothing goes to `failed_jobs`. The notification stays in the app (design 6.5 point 4).
 * - When the provider refuses the mail for a quota, the transport throws its own exception with the error of the provider
 *   inside. The middleware reads the error type: `monthly_quota_exceeded` holds the notification mail until the first
 *   day of the next month, `daily_quota_exceeded` until the next day (00:00 UTC), and the job ends without a retry. Any
 *   other error is thrown again, for the normal backoff.
 *
 * Account mail (the sign-in link, the invitations) does not go through it: it is never held.
 */
final class HoldNotificationMail
{
    public const string MONTHLY_QUOTA_EXCEEDED = 'monthly_quota_exceeded';

    public const string DAILY_QUOTA_EXCEEDED = 'daily_quota_exceeded';

    /**
     * The chain of previous exceptions is read this deep at most
     */
    private const int MAX_DEPTH = 10;

    public function handle(object $job, Closure $next): void
    {
        $budget = app(ChecksMailBudgets::class);

        if ($budget->isHeld()) {
            Log::info('A notification mail is not sent: the mail budget of the month holds notification mail', ['mailable' => self::nameOf($job)]);

            return;
        }

        try {
            $next($job);
        } catch (TransportExceptionInterface $exception) {
            $type = self::quotaRefusal($exception);
            if ($type === null) {
                throw $exception;
            }

            $now = CarbonImmutable::now('UTC');
            $budget->holdUntil($type === self::MONTHLY_QUOTA_EXCEEDED ? $now->startOfMonth()->addMonthNoOverflow() : $now->startOfDay()->addDay(), $type);
        }
    }

    /**
     * The error type of the provider when it refused the mail for a quota, else null. Exact text, no pattern
     */
    private static function quotaRefusal(Throwable $exception): ?string
    {
        $current = $exception;
        for ($depth = 0; $current !== null && $depth < self::MAX_DEPTH; $depth++) {
            if ($current instanceof ErrorException) {
                try {
                    $type = $current->getErrorType();
                } catch (Throwable) {
                    // An error of the provider without a type or a name
                    $type = null;
                }
                if ($type === self::MONTHLY_QUOTA_EXCEEDED || $type === self::DAILY_QUOTA_EXCEEDED) {
                    return $type;
                }
            }
            $current = $current->getPrevious();
        }

        return null;
    }

    private static function nameOf(object $job): string
    {
        return $job instanceof SendQueuedMailable ? $job->displayName() : $job::class;
    }
}
