<?php

namespace App\Mail;

use App\Constants;
use App\Jobs\Middleware\HoldNotificationMail;
use App\Support\MailSettings;
use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Factory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

/**
 * The base of every notification mail (#1477, ADR 0054; the first one comes with slice 4c). It fixes how the mail is
 * delivered, so that a subclass only builds its content:
 *
 * - the queue: `config('mail.queue')` (`mail` by default, read by a worker of its own), set when the mail is queued;
 * - the middleware: the hold of the mail budget, then the rate limiter `mail` (`AppServiceProvider`). A job that the
 *   limiter releases is not failed;
 * - the retries: until Constants::MAIL_NOTIFICATION_RETRY_HOURS after the mail was due (the time it was queued, or the
 *   end of its delay). In that time the worker ignores its `--tries`, so the releases of the limiter never fail a job.
 *   After it, the worker fails the job the next time it takes it, also a job that never ran: a mail that waited for too
 *   long (a stopped worker, a long backlog) is not sent late. It goes to `failed_jobs` and the notification stays in the
 *   app. The third real exception fails the job too; the waits after an exception come from `backoff()`.
 *
 * Account mail (`MagicLink`, `CommunityInvitation`, `CommunityOwnershipTransferred`, `MailBudgetAlert`, the address
 * verification) does not extend it: it stays on `default`, with no limiter and no hold.
 */
abstract class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Only real exceptions count: a release by the rate limiter is not one
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * When a delayed mail is due (`later()`); null for a mail that is queued to go at once. The retry limit counts from it
     */
    protected ?CarbonImmutable $dueAt = null;

    /**
     * {@inheritDoc}
     */
    public function queue(Factory $queue)
    {
        $this->onQueue(MailSettings::queue());

        return parent::queue($queue);
    }

    /**
     * {@inheritDoc}
     */
    public function later($delay, Factory $queue)
    {
        $this->onQueue(MailSettings::queue());
        $this->dueAt = match (true) {
            $delay instanceof DateTimeInterface => CarbonImmutable::instance($delay),
            $delay instanceof DateInterval => CarbonImmutable::now()->add($delay),
            default => CarbonImmutable::now()->addSeconds($delay),
        };

        return parent::later($delay, $queue);
    }

    /**
     * The hold comes first: a held mail does not use a place of the rate limit
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new HoldNotificationMail, new RateLimited('mail')];
    }

    /**
     * Computed when the mail is queued: Constants::MAIL_NOTIFICATION_RETRY_HOURS after the mail is due
     */
    public function retryUntil(): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $from = $this->dueAt !== null && $this->dueAt->greaterThan($now) ? $this->dueAt : $now;

        return $from->addHours(Constants::MAIL_NOTIFICATION_RETRY_HOURS);
    }

    /**
     * Seconds of wait after an exception, by the number of the attempt. A release of the rate limiter counts as an attempt
     * too: a mail with no release waits 1 minute after its first exception and 5 minutes after the second one (the third
     * one fails it), and a mail that was released before waits longer
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 1800];
    }
}
