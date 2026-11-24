<?php

namespace App\Mail;

use App\Jobs\Middleware\HideMailErrorText;
use App\Mail\Concerns\HasDeliveryRow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The mail of a change of the address to an address that another account has (ADR 0058): it goes to the new address in
 * place of the link, and says that nothing changed. No link. An account mail of level 0 (ADR 0054): the queue `default`,
 * never held. It holds the address of the recipient, so it is encrypted in the queue (ADR 0064). A mail that fails keeps no
 * recipient address (HideMailErrorText).
 */
class EmailAddressHasAccount extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use HasDeliveryRow, Queueable, SerializesModels;

    /**
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
        return $this->markdown('emails.email-address-has-account')->subject(__('This address already has an account'));
    }
}
