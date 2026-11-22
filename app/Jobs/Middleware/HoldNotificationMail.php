<?php

namespace App\Jobs\Middleware;

use App\Contracts\Mail\ChecksMailBudgets;
use App\Support\MailQuotaRefusal;
use Closure;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * The hold of the notification mail (#1477, ADR 0054), the first middleware of every `NotificationMail`, before the rate
 * limiter.
 *
 * - While the mail budget of the month holds notification mail (`ChecksMailBudgets::isHeld()`), the job ends as done:
 *   nothing is sent and nothing goes to `failed_jobs`. The notification stays in the app (design 6.5 point 4).
 * - When the provider refuses the mail for a quota, the transport throws its own exception with the error of the provider
 *   inside. The middleware reads the error type (MailQuotaRefusal): `monthly_quota_exceeded` holds the notification mail
 *   until the first day of the next month, `daily_quota_exceeded` until the next day (00:00 UTC), and the job ends
 *   without a retry. Any other error is thrown again, for the normal backoff.
 *
 * Account mail (the sign-in link, a single invitation) does not go through it: it is never held. The mail of the
 * invitations from a file has its own hold (HoldInvitationImportMail, #1390).
 */
final class HoldNotificationMail
{
    public const string MONTHLY_QUOTA_EXCEEDED = MailQuotaRefusal::MONTHLY_QUOTA_EXCEEDED;

    public const string DAILY_QUOTA_EXCEEDED = MailQuotaRefusal::DAILY_QUOTA_EXCEEDED;

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
            $type = MailQuotaRefusal::typeOf($exception);
            if ($type === null) {
                throw $exception;
            }

            $budget->holdUntil(MailQuotaRefusal::holdEnd($type), $type);
        }
    }

    private static function nameOf(object $job): string
    {
        return $job instanceof SendQueuedMailable ? $job->displayName() : $job::class;
    }
}
