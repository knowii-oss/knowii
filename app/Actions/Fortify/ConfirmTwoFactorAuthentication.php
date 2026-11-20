<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication as FortifyConfirmTwoFactorAuthentication;

/**
 * The confirmation of the second factor: the step that turns it on (`confirm` is on in config/fortify.php). An account that
 * never verified an address cannot turn it on (ADR 0059): the form gets the error of a wrong code, and nothing is stored.
 * Its address is verified first. The model of the request decides. Every other account gets the action of Fortify.
 */
class ConfirmTwoFactorAuthentication extends FortifyConfirmTwoFactorAuthentication
{
    /**
     * @param  Authenticatable  $user
     * @param  string  $code
     *
     * @throws ValidationException
     */
    public function __invoke($user, $code): void
    {
        if ($user instanceof User && $user->addressWasNeverVerified()) {
            // The error of a wrong code, in the bag of the form (as the action of Fortify)
            throw ValidationException::withMessages([
                'code' => [__('The provided two factor authentication code was invalid.')],
            ])->errorBag('confirmTwoFactorAuthentication');
        }

        parent::__invoke($user, $code);
    }
}
