<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The link that confirms a new email address (#1706, ADR 0058). It goes to the new address. An account mail of level 0
 * (ADR 0054): the default queue, never held.
 */
class ConfirmNewEmailAddress extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $url, public int $minutes) {}

    /**
     * @return $this
     */
    public function build(): static
    {
        return $this->markdown('emails.confirm-new-email-address', [
            'url' => $this->url,
            'minutes' => $this->minutes,
        ])->subject(__('Confirm your new email address'));
    }
}
