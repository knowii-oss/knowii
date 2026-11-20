<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes as FortifyGenerateNewRecoveryCodes;

/**
 * New recovery codes of the second factor. An account whose address was never verified cannot get them (ADR 0059): the
 * answer is the refusal of the framework (403), and nothing is stored. The model of the request decides. Every other
 * account gets the action of Fortify.
 */
class GenerateNewRecoveryCodes extends FortifyGenerateNewRecoveryCodes
{
    /**
     * @param  Authenticatable  $user
     *
     * @throws AuthorizationException
     */
    public function __invoke($user): void
    {
        if ($user instanceof User && $user->addressWasNeverVerified()) {
            throw new AuthorizationException;
        }

        parent::__invoke($user);
    }
}
