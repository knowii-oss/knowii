<?php

namespace App\Jobs;

use App\Constants;
use App\Contracts\InvitationImports\SendsInvitationImportMails;
use App\Jobs\Middleware\HideMailErrorText;
use App\Jobs\Middleware\HoldInvitationImportMail;
use App\Support\MailSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

/**
 * The mail of one row of an import of invitations (#1390, ADR 0061). It holds the id of the row and the number of the
 * time that the row went to the queue (`queued_count`), never the address: it acts only on that time of the row, so a
 * job of an earlier time of a row that went to the queue again changes nothing. It runs on the mail queue
 * (MailSettings::queue()), behind the hold of the import mail and the rate limiter `mail`, with the retries of
 * NotificationMail: until Constants::MAIL_NOTIFICATION_RETRY_HOURS after it was queued, at most 3 real exceptions, the
 * same backoff. After the last one, the row is `failed`. An attempt runs at most Constants::MAIL_JOB_TIMEOUT_SECONDS.
 *
 * A single invitation stays level 0 on `default`, never held (ADR 0054 point 1): a manager asks for the mails of a
 * file, not the person who gets one, and a file holds up to 1000 of them.
 */
class SendInvitationImportMail implements ShouldQueue
{
    use Queueable;

    /**
     * Only real exceptions count: a release by the rate limiter is not one
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * InvitationImports::staleQueuedBefore() counts on it
     *
     * @var int
     */
    public $timeout = Constants::MAIL_JOB_TIMEOUT_SECONDS;

    public function __construct(public readonly int $rowId, public readonly int $queuedCount)
    {
        $this->onQueue(MailSettings::queue());
    }

    /**
     * A failed send keeps no text of the error, which can name the recipient (HideMailErrorText, outside the others, so
     * that the hold still reads a refusal for a quota). The hold comes before the limit: a held mail does not use a place
     * of the rate limit
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new HideMailErrorText, new HoldInvitationImportMail, new RateLimited('mail')];
    }

    /**
     * Computed when the job is queued
     */
    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::now()->addHours(Constants::MAIL_NOTIFICATION_RETRY_HOURS);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 1800];
    }

    public function handle(SendsInvitationImportMails $mails): void
    {
        $mails->send($this->rowId, $this->queuedCount);
    }

    public function failed(?Throwable $exception): void
    {
        app(SendsInvitationImportMails::class)->fail($this->rowId, $this->queuedCount);
    }
}
