<?php

namespace App\Mail;

use App\Constants;
use App\Enums\KnowiiMailDeliveryStatus;
use App\Enums\KnowiiMailKind;
use App\Enums\KnowiiNotificationEmailGroup;
use App\Jobs\Middleware\HideMailErrorText;
use App\Jobs\Middleware\HoldNotificationMail;
use App\Jobs\Middleware\SkipSuppressedNotificationMail;
use App\Mail\Concerns\HasDeliveryRow;
use App\Models\MailDelivery;
use App\Models\User;
use App\Support\InstanceValues;
use App\Support\MailSettings;
use App\Support\MailStopLink;
use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Factory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Visus\Cuid2\Cuid2;

/**
 * The base of every notification mail (#1477, ADR 0054; the first one comes with slice 4c). It fixes how the mail is
 * delivered, so that a subclass only builds its content:
 *
 * - the queue: `config('mail.queue')` (`mail` by default, read by a worker of its own), set when the mail is queued;
 * - the middleware: the hold of the mail budget, then the skip of a suppressed address or a stopped group (#1478), then
 *   the rate limiter `mail` (`AppServiceProvider`). A job that the limiter releases is not failed;
 * - the retries: until Constants::MAIL_NOTIFICATION_RETRY_HOURS after the mail was due (the time it was queued, or the
 *   end of its delay). In that time the worker ignores its `--tries`, so the releases of the limiter never fail a job.
 *   After it, the worker fails the job the next time it takes it, also a job that never ran: a mail that waited for too
 *   long (a stopped worker, a long backlog) is not sent late. It goes to `failed_jobs` and the notification stays in the
 *   app. The third real exception fails the job too; the waits after an exception come from `backoff()`.
 *
 * - at most once (#1478, ADR 0054 consequence 4): the row of the delivery log is written `queued` before the mail goes to
 *   the queue, of the account and the group of the mail (and of its notification, when it has one: one row per
 *   notification). The job claims it with one conditional update (`queued` or `failed` to `sending`) just before the
 *   send; when nothing changes, another run sent it or sends it, and the job ends without a send. The listener of
 *   MessageSent sets `sent`, an error of the transport sets `failed` (the next try claims it again), the hold sets
 *   `held` and the skip sets `skipped`. A crash after the provider took the mail and before the listener leaves the row
 *   `sending`: the mail is not sent again.
 *
 * A subclass names its account (account()) and its group (emailGroup()).
 *
 * Account mail (`MagicLink`, `CommunityInvitation`, `CommunityOwnershipTransferred`, `MailBudgetAlert`, the address
 * verification) does not extend it: it stays on `default`, with no limiter and no hold.
 */
abstract class NotificationMail extends Mailable implements ShouldQueue
{
    use HasDeliveryRow, Queueable, SerializesModels;

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
     * The id of the notification that the mail is about, or null: one mail per notification
     */
    public ?int $notificationId = null;

    /**
     * The account that gets the mail
     */
    abstract public function account(): User;

    /**
     * The notification email group of the mail: what its stop link stops
     */
    abstract public function emailGroup(): KnowiiNotificationEmailGroup;

    /**
     * The mail is about this notification: it goes to the queue once
     */
    public function forNotification(int $notificationId): static
    {
        $this->notificationId = $notificationId;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function queue(Factory $queue)
    {
        $this->onQueue(MailSettings::queue());
        if (! $this->writeDeliveryRow(KnowiiMailDeliveryStatus::Queued)) {
            return null;
        }

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
        if (! $this->writeDeliveryRow(KnowiiMailDeliveryStatus::Queued)) {
            return null;
        }

        return parent::later($delay, $queue);
    }

    /**
     * The stop link of the mail: the group of the mail, for its account. Built when the mail is sent, never stored in the
     * queued job (ADR 0064 point 11)
     */
    public function stopLink(): string
    {
        return MailStopLink::for($this->account(), $this->emailGroup());
    }

    /**
     * The row of the delivery log, and the one-click stop of RFC 8058 (#1478, design 5.6 point 9): `List-Unsubscribe` with
     * the stop link of the group, the same address as the footer link, and `List-Unsubscribe-Post`. Account mail has
     * neither header
     */
    public function headers(): Headers
    {
        return new Headers(text: $this->deliveryHeaders() + [
            'List-Unsubscribe' => '<'.$this->stopLink().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    /**
     * The values of the footer partial `emails.partials.notification-footer`: the stop link, the settings and the line of
     * the operator ("<operator>, <postal address>", or the operator alone without an address)
     *
     * @return array{stopUrl: string, settingsUrl: string, operatorLine: string}
     */
    public function footerData(): array
    {
        $operator = InstanceValues::operator();
        $address = InstanceValues::postalAddress();

        return [
            'stopUrl' => $this->stopLink(),
            'settingsUrl' => route('profile.notifications'),
            'operatorLine' => $address === '' ? $operator : $operator.', '.$address,
        ];
    }

    /**
     * Claims the row of the delivery log, then sends. A mail without a row (sent without the queue) gets one now
     *
     * {@inheritDoc}
     */
    public function send($mailer)
    {
        if ($this->deliveryCuid === null) {
            if (! $this->writeDeliveryRow(KnowiiMailDeliveryStatus::Sending)) {
                return null;
            }
        } elseif (MailDelivery::query()
            ->where('cuid', $this->deliveryCuid)
            ->whereIn('status', [KnowiiMailDeliveryStatus::Queued->value, KnowiiMailDeliveryStatus::Failed->value])
            ->update(['status' => KnowiiMailDeliveryStatus::Sending->value, 'updated_at' => now()]) !== 1) {
            Log::info('A notification mail is not sent: its row of the delivery log was claimed before', ['delivery' => $this->deliveryCuid]);

            return null;
        }

        try {
            return parent::send($mailer);
        } catch (Throwable $exception) {
            $this->markDelivery(KnowiiMailDeliveryStatus::Failed);

            throw $exception;
        }
    }

    /**
     * Sets the state of the row of the delivery log (held, skipped, failed). Never throws
     */
    public function markDelivery(KnowiiMailDeliveryStatus $status): void
    {
        if ($this->deliveryCuid === null) {
            return;
        }

        try {
            MailDelivery::query()->where('cuid', $this->deliveryCuid)->where('status', '!=', KnowiiMailDeliveryStatus::Sent->value)->update(['status' => $status->value, 'updated_at' => now()]);
        } catch (Throwable $exception) {
            Log::error('The state of a row of the delivery log could not be written', ['delivery' => $this->deliveryCuid, 'exception' => $exception::class]);
        }
    }

    /**
     * A failed send keeps no text of the error, which can name the recipient (HideMailErrorText, outside the others, so
     * that the hold still reads a refusal for a quota). The hold comes first: a held mail does not use a place of the rate
     * limit. Then the skip of a suppressed address or a stopped group, before the limit too
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new HideMailErrorText, new HoldNotificationMail, new SkipSuppressedNotificationMail, new RateLimited('mail')];
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

    /**
     * Writes the row of the delivery log of the mail. False when its notification has a row already: the mail went to the
     * queue before, and does not go again
     */
    private function writeDeliveryRow(KnowiiMailDeliveryStatus $status): bool
    {
        try {
            // In a transaction of its own (a savepoint in the transaction of a caller), so that a refused insert leaves the
            // transaction of the caller usable
            $this->deliveryCuid = DB::transaction(fn (): string => MailDelivery::query()->forceCreate([
                'cuid' => (new Cuid2(24))->toString(),
                'kind' => KnowiiMailKind::ofGroup($this->emailGroup()),
                'user_id' => $this->account()->id,
                'notification_id' => $this->notificationId,
                'channel' => MailDelivery::CHANNEL_EMAIL,
                'status' => $status,
            ])->cuid);
        } catch (UniqueConstraintViolationException) {
            Log::info('A notification mail is not queued again: its notification has a row of the delivery log', ['notification' => $this->notificationId]);

            return false;
        }

        return true;
    }
}
