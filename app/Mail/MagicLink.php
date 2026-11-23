<?php

namespace App\Mail;

use App\Actions\Auth\MagicLinks;
use App\Mail\Concerns\HasDeliveryRow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The email with a sign-in link (#703, ADR 0023). It holds the token, so it is encrypted in the queue (ADR 0064).
 */
class MagicLink extends Mailable implements ShouldBeEncrypted
{
    use HasDeliveryRow, Queueable, SerializesModels;

    public function __construct(public string $token) {}

    /**
     * @return $this
     */
    public function build(): static
    {
        return $this->markdown('emails.magic-link', [
            'url' => route('login.link.show', ['token' => $this->token]),
            'minutes' => MagicLinks::VALIDITY_MINUTES,
        ])->subject(__('Your sign-in link for Knowii'));
    }
}
