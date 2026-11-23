<?php

namespace App\Jobs\Middleware;

use App\Enums\KnowiiMailDeliveryStatus;
use App\Mail\NotificationMail;
use App\Models\NotificationEmailChoice;
use App\Support\EmailSuppression;
use Closure;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Log;

/**
 * The stop at the last moment of a notification mail (#1478, ADR 0054, design 6.2 point 5), after the hold and before the
 * rate limiter: when the address of the account is suppressed (App\Support\EmailSuppression), or when the member stopped
 * the group of the mail, the job ends as done, with its row of the delivery log `skipped`. Nothing is sent, nothing goes
 * to `failed_jobs`, and the notification stays in the app. It runs each time the job runs.
 */
final class SkipSuppressedNotificationMail
{
    public function handle(object $job, Closure $next): void
    {
        $mail = $job instanceof SendQueuedMailable ? $job->mailable : null;
        if (! $mail instanceof NotificationMail) {
            $next($job);

            return;
        }

        $user = $mail->account();
        $reason = match (true) {
            EmailSuppression::appliesTo($user) => 'the address is suppressed',
            NotificationEmailChoice::query()->where('user_id', $user->id)->where('group', $mail->emailGroup()->value)->where('state', NotificationEmailChoice::STATE_OFF)->exists() => 'the member stopped the group',
            default => null,
        };
        if ($reason === null) {
            $next($job);

            return;
        }

        $mail->markDelivery(KnowiiMailDeliveryStatus::Skipped);
        Log::info('A notification mail is not sent: '.$reason, ['delivery' => $mail->deliveryCuid, 'user' => $user->cuid, 'group' => $mail->emailGroup()->value]);
    }
}
