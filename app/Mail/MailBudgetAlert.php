<?php

namespace App\Mail;

use App\Jobs\Middleware\HideMailErrorText;
use App\Mail\Concerns\HasDeliveryRow;
use App\Support\MailBudgetCheck;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The alert of the mail budget to the operator (#1477, ADR 0054), sent by `mail:check-budget` to the addresses of
 * MAIL_ALERT_TO only, never to a member: at 80% of the monthly quota, and when notification mail is held. It is account
 * mail (level 0): a plain mailable on the queue `default`, never held, so it still goes while notification mail is held.
 * It holds an address of the operator, so it is encrypted in the queue (ADR 0064). A mail that fails keeps no recipient
 * address (HideMailErrorText).
 */
class MailBudgetAlert extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use HasDeliveryRow, Queueable, SerializesModels;

    public function __construct(
        public CarbonImmutable $month,
        public int $used,
        public int $quota,
        public int $percent,
        public string $source,
        public int $holdPercent,
        public ?CarbonImmutable $heldUntil,
    ) {}

    /**
     * A mail that fails keeps no recipient address: neither the log nor `failed_jobs` gets the text of the error
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new HideMailErrorText];
    }

    /**
     * @return $this
     */
    public function build(): static
    {
        // The public properties also reach the view: these names are other ones
        return $this->markdown('emails.mail-budget-alert', [
            'monthName' => $this->month->format('F Y'),
            'usedText' => self::number($this->used),
            'quotaText' => self::number($this->quota),
            'percentUsed' => $this->percent,
            'stopPercent' => $this->holdPercent,
            'fromProvider' => $this->source === MailBudgetCheck::SOURCE_PROVIDER,
            'pausedUntil' => $this->heldUntil !== null ? $this->heldUntil->utc()->format('j F Y, H:i').' UTC' : null,
        ])->subject($this->heldUntil !== null
            ? __('Notification emails are paused: :percent% of the email quota of :month is used', ['percent' => $this->percent, 'month' => $this->month->format('F Y')])
            : __(':percent% of the email quota of :month is used', ['percent' => $this->percent, 'month' => $this->month->format('F Y')]));
    }

    /**
     * 2 400, as in the approved text
     */
    private static function number(int $value): string
    {
        return number_format($value, 0, '.', ' ');
    }
}
