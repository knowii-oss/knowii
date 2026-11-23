<?php

namespace App\Notifications;

use App\Jobs\Middleware\HideMailErrorText;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The password reset mail: the mail of the framework, with the same words and the same link. The job of the reset form
 * (`App\Jobs\SendPasswordResetLink`) queues it, and it goes through the queue as the sign-in link (`App\Mail\MagicLink`):
 * the queue `default`, with no limiter and no hold of the mail budget. Its link holds the token, so it is encrypted in
 * the queue (ADR 0064).
 */
class ResetPasswordLink extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * A mail that fails keeps no recipient address: neither the log nor `failed_jobs` gets the text of the error
     *
     * @return list<object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        return [new HideMailErrorText];
    }
}
