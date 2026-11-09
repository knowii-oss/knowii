<?php

namespace App\Mail;

use App\Actions\Auth\MagicLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The email with a sign-in link (#703, ADR 0023).
 */
class MagicLink extends Mailable
{
    use Queueable, SerializesModels;

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
