<?php

namespace App\Jobs\Middleware;

use App\Contracts\InvitationImports\SendsInvitationImportMails;
use App\Contracts\Mail\ChecksMailBudgets;
use App\Jobs\SendInvitationImportMail;
use App\Support\MailQuotaRefusal;
use Closure;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * The hold of the mail of the invitations from a file (#1390, ADR 0061), the first middleware of
 * SendInvitationImportMail, before the rate limiter. It works as HoldNotificationMail (ADR 0054 point 6), and the row
 * says what happened:
 *
 * - while the mail budget holds mail (`ChecksMailBudgets::isHeld()`), the row is `held` and the job ends as done;
 * - when the provider refuses the mail for a quota (MailQuotaRefusal), the budget holds the mail as for a notification,
 *   the row is `held`, and the job ends without a retry. Any other error is thrown again, for the normal backoff.
 *
 * "Send the mails that were not sent" queues the held rows again.
 */
final class HoldInvitationImportMail
{
    public function handle(object $job, Closure $next): void
    {
        $budget = app(ChecksMailBudgets::class);
        $mail = $job instanceof SendInvitationImportMail ? $job : null;

        if ($budget->isHeld()) {
            Log::info('A mail of an invitation import is not sent: the mail budget holds mail');
            self::hold($mail);

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
            self::hold($mail);
        }
    }

    private static function hold(?SendInvitationImportMail $mail): void
    {
        if ($mail !== null) {
            app(SendsInvitationImportMails::class)->hold($mail->rowId, $mail->queuedCount);
        }
    }
}
