<?php

namespace App\Mail;

use App\Jobs\Middleware\HideMailErrorText;
use App\Mail\Concerns\HasDeliveryRow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The mail of a sign-up for an address that has an account (ADR 0065): it says so, with plain links to the sign-in page and
 * to the reset page. No token and no signed link. An account mail of level 0 (ADR 0054): the queue `default`, never held.
 * It holds the address of the recipient, so it is encrypted in the queue (ADR 0064). A mail that fails keeps no recipient
 * address (HideMailErrorText).
 */
class SignUpAccountExists extends Mailable implements ShouldBeEncrypted
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
        return $this->markdown('emails.sign-up-account-exists', [
            'signInUrl' => route('login'),
            'resetUrl' => route('password.request'),
        ])->subject(__('You already have an account on Knowii'));
    }
}
