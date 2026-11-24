<?php

namespace App\Actions\Fortify;

use App\Contracts\Auth\EndsOtherSessions;
use App\Models\User;
use App\Support\SecondFactor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes as FortifyGenerateNewRecoveryCodes;

/**
 * New recovery codes of the second factor. An account whose address was never verified cannot get them (ADR 0059): the
 * answer is the refusal of the framework (403), and nothing is stored. The model of the request decides. Every other
 * account gets the action of Fortify.
 *
 * When the second factor is on, new recovery codes sign out the other devices of the account (ADR 0066): the other sessions
 * end, and a "Remember me" cookie of before signs nobody in. The present session goes on. Before the confirmation of the
 * second factor the codes are not in use, and new ones end no session. When the account changed elsewhere while the
 * request ran, nothing is stored and the session of the request ends.
 */
class GenerateNewRecoveryCodes extends FortifyGenerateNewRecoveryCodes
{
    /**
     * @param  Authenticatable  $user
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function __invoke($user): void
    {
        if ($user instanceof User && $user->addressWasNeverVerified()) {
            throw new AuthorizationException;
        }

        if (! $user instanceof User || ! SecondFactor::isOnFor($user)) {
            parent::__invoke($user);

            return;
        }

        // The write of Fortify and the new version are one change: both are stored, or none (EndsOtherSessions)
        app(EndsOtherSessions::class)->endFor($user, newRememberToken: true, write: function () use ($user): void {
            parent::__invoke($user);
        });
    }
}
