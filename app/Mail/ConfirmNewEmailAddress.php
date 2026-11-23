<?php

namespace App\Mail;

use App\Mail\Concerns\HasDeliveryRow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The link that confirms a new email address (#1706, ADR 0058). It goes to the new address. An account mail of level 0
 * (ADR 0054): the default queue, never held. It holds the signed link, so it is encrypted in the queue (ADR 0064).
 */
class ConfirmNewEmailAddress extends Mailable implements ShouldBeEncrypted
{
    use HasDeliveryRow, Queueable, SerializesModels;

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
