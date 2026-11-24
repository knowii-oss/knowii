<?php

namespace App\Mail;

use App\Jobs\Middleware\HideMailErrorText;
use App\Mail\Concerns\HasDeliveryRow;
use App\Support\InstanceValues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The notice that goes to the address of before when a change of the email address is confirmed (#1706, ADR 0058). It
 * shows the new address masked. An account mail of level 0 (ADR 0054): the default queue, never held. It holds the
 * address of before, so it is encrypted in the queue (ADR 0064). A mail that fails keeps no recipient address
 * (HideMailErrorText).
 */
class EmailAddressChanged extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use HasDeliveryRow, Queueable, SerializesModels;

    /**
     * @param  string  $maskedAddress  The new address with most of the name hidden: "a***@example.com"
     */
    public function __construct(public string $maskedAddress) {}

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
        $contact = InstanceValues::contactEmail();

        return $this->markdown('emails.email-address-changed', [
            'address' => $this->maskedAddress,
            'contact' => $contact !== '' ? $contact : null,
        ])->subject(__('The email address of your account on Knowii changed'));
    }
}
