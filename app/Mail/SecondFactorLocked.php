<?php

namespace App\Mail;

use App\Jobs\Middleware\HideMailErrorText;
use App\Mail\Concerns\HasDeliveryRow;
use App\Support\SecondFactorTries;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the member that the second step of the sign-in of the account is closed (App\Support\SecondFactorTries): the first
 * factor was right, then wrong codes of two-factor authentication reached the limit of the hour. It says until when, and
 * links to the page of a password reset. One mail at most for each closed hour. An account mail (ADR 0054): the default
 * queue, never held, and not under the caps of the account mails that a form asks for. It holds no IP address and no place.
 * It goes through the queue, encrypted, and a mail that fails keeps no recipient address (HideMailErrorText, ADR 0069). It
 * prints no text that a person typed: the zone of the time is a name of the IANA list (KnownTimeZone).
 */
class SecondFactorLocked extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use HasDeliveryRow, Queueable, SerializesModels;

    /**
     * @param  CarbonImmutable  $until  When the second step opens again
     * @param  string  $timeZone  The zone in which the mail shows that time: the one of the member, else the one of the
     *                            instance
     */
    public function __construct(public CarbonImmutable $until, public string $timeZone) {}

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
        return $this->markdown('emails.second-factor-locked', [
            'count' => SecondFactorTries::lockCount(),
            'closedUntil' => $this->until->setTimezone($this->timeZone)->format('Y-m-d H:i').' ('.$this->timeZone.')',
            'resetUrl' => route('password.request'),
        ])->subject(__('Wrong two-factor codes on your account on Knowii'));
    }
}
