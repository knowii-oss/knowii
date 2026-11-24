<?php

namespace App\Actions\Fortify;

use App\Contracts\Auth\EndsOtherSessions;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication as FortifyConfirmTwoFactorAuthentication;

/**
 * The confirmation of the second factor: the step that turns it on (`confirm` is on in config/fortify.php). An account that
 * never verified an address cannot turn it on (ADR 0059): the form gets the error of a wrong code, and nothing is stored.
 * Its address is verified first. The model of the request decides. Every other account gets the action of Fortify. A code
 * that is no text (a list) gets the same error.
 *
 * Turning the second factor on signs out the other devices of the account (ADR 0066): the other sessions end, and a
 * "Remember me" cookie of before signs nobody in, so every other device needs the code from now on. The present session
 * goes on. When the account changed elsewhere while the request ran, nothing is stored and the session of the request ends.
 */
class ConfirmTwoFactorAuthentication extends FortifyConfirmTwoFactorAuthentication
{
    /**
     * @param  Authenticatable  $user
     * @param  array<array-key, array<array-key, scalar|null>|scalar|null>|scalar|null  $code  What the request holds as the code: a text, or a value that is no code
     *
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public function __invoke($user, $code): void
    {
        // A client that sends JSON can send the digits as a number
        $code = is_int($code) ? (string) $code : $code;

        if (! is_string($code) || ($user instanceof User && $user->addressWasNeverVerified())) {
            // The error of a wrong code, in the bag of the form (as the action of Fortify)
            throw ValidationException::withMessages([
                'code' => [__('The provided two factor authentication code was invalid.')],
            ])->errorBag('confirmTwoFactorAuthentication');
        }

        if (! $user instanceof User) {
            parent::__invoke($user, $code);

            return;
        }

        // The write of Fortify and the new version are one change: both are stored, or none (EndsOtherSessions)
        app(EndsOtherSessions::class)->endFor($user, newRememberToken: true, write: function () use ($user, $code): void {
            parent::__invoke($user, $code);
        });
    }
}
