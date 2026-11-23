<?php

namespace App\Mail;

use App\Mail\Concerns\HasDeliveryRow;
use App\Support\InstanceValues;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The notice that goes to the address of before when a change of the email address is confirmed (#1706, ADR 0058). It
 * shows the new address masked. An account mail of level 0 (ADR 0054): the default queue, never held.
 */
class EmailAddressChanged extends Mailable
{
    use HasDeliveryRow, Queueable, SerializesModels;

    /**
     * @param  string  $maskedAddress  The new address with most of the name hidden: "a***@example.com"
     */
    public function __construct(public string $maskedAddress) {}

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
