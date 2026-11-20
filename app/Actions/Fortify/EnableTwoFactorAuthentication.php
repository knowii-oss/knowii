<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication as FortifyEnableTwoFactorAuthentication;

/**
 * "Enable" of the second factor: the step that stores a secret and recovery codes. An account whose address was never
 * verified cannot take it (ADR 0059): the answer is the refusal of the framework (403), and nothing is stored. Its address
 * is verified first. The model of the request decides. Every other account gets the action of Fortify.
 */
class EnableTwoFactorAuthentication extends FortifyEnableTwoFactorAuthentication
{
    /**
     * @param  Authenticatable  $user
     * @param  bool  $force
     *
     * @throws AuthorizationException
     */
    public function __invoke($user, $force = false): void
    {
        if ($user instanceof User && $user->addressWasNeverVerified()) {
            throw new AuthorizationException;
        }

        parent::__invoke($user, $force);
    }
}
