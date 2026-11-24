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
 * The mail of a sign-up for an address that no account has (ADR 0065): the link to the page where the person chooses the
 * password, and the account is made. An account mail of level 0 (ADR 0054): the queue `default`, never held. It holds the
 * token, so it is encrypted in the queue (ADR 0064). A mail that fails keeps no recipient address (HideMailErrorText).
 */
class FinishSignUp extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use HasDeliveryRow, Queueable, SerializesModels;

    public function __construct(#[\SensitiveParameter] public string $token, public int $minutes) {}

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
        return $this->markdown('emails.finish-sign-up', [
            'url' => route('register.link.show', ['token' => $this->token]),
            'minutes' => $this->minutes,
        ])->subject(__('Finish your sign-up to Knowii'));
    }
}
