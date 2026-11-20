<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\Landing;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * The mail of the address check (#1612, ADR 0057): the mail of the framework, with the same words, the same queue and
 * the same life of the link. Its link carries the landing: the page of a community that the person was reading when the
 * mail was sent. The link is signed with the landing in it, so nobody can change the landing. The landing is written
 * without a character that needs an escape: the query of the link is the same after a program writes it again.
 */
class VerifyEmailAddress extends VerifyEmail
{
    /**
     * @param  string|null  $landing  A path that Landing::pathOf() gave, or null: the link is then the one of before
     */
    public function __construct(public readonly ?string $landing = null) {}

    /**
     * The same signed, temporary link as the framework builds, with the landing as one more parameter
     */
    protected function verificationUrl($notifiable): string
    {
        // The form of the landing in a link: only letters, digits, "-" and ".", so the query needs no escape
        $landing = Landing::linkValueOf($this->landing);
        if ($landing === null || static::$createUrlCallback !== null || ! $notifiable instanceof User) {
            return parent::verificationUrl($notifiable);
        }

        $minutes = Config::get('auth.verification.expire', 60);

        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(is_int($minutes) ? $minutes : 60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
                Landing::LINK_PARAMETER => $landing,
            ]
        );
    }
}
