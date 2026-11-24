<?php

namespace App\Actions\Fortify;

use App\Contracts\Auth\EndsOtherSessions;
use App\Models\User;
use App\Support\SecondFactor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as FortifyDisableTwoFactorAuthentication;

/**
 * "Disable" of the second factor. When the second factor was on, turning it off signs out the other devices of the account
 * (ADR 0066): the other sessions end, and a "Remember me" cookie of before signs nobody in. The present session goes on. A
 * set-up that was never confirmed changed nothing for a sign-in, so its removal ends no session. When the account changed
 * elsewhere while the request ran, nothing is stored and the session of the request ends.
 */
class DisableTwoFactorAuthentication extends FortifyDisableTwoFactorAuthentication
{
    public function __construct(private readonly EndsOtherSessions $endsOtherSessions) {}

    /**
     * @param  Authenticatable  $user
     *
     * @throws AuthenticationException
     */
    public function __invoke($user): void
    {
        if (! $user instanceof User || ! SecondFactor::isOnFor($user)) {
            parent::__invoke($user);

            return;
        }

        // The write of Fortify and the new version are one change: both are stored, or none (EndsOtherSessions)
        $this->endsOtherSessions->endFor($user, newRememberToken: true, write: function () use ($user): void {
            parent::__invoke($user);
        });
    }
}
