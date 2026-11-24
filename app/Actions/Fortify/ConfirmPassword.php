<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\PasswordTries;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Laravel\Fortify\Actions\ConfirmPassword as FortifyConfirmPassword;

/**
 * The check of the current password of a signed-in account, for every caller of the action of Fortify: the confirmation of
 * the password (`POST /user/confirm-password`), "Log out other browser sessions" and the deletion of the account, on the
 * page and on the API. The password is checked with the tries of what signed the request in (App\Support\PasswordTries):
 * a wrong one counts, and a session ends at the fifth wrong one of its count in 15 minutes. A value that is no text (a
 * list, or no value at all) is never the password: it is not checked, and it is no try.
 */
class ConfirmPassword extends FortifyConfirmPassword
{
    /**
     * @param  Authenticatable|null  $user
     * @param  array<array-key, array<array-key, scalar|null>|scalar|null>|scalar|null  $password  What the request holds as the password: a text, or a value that is no password
     *
     * @throws AuthenticationException
     * @throws ThrottleRequestsException
     */
    public function __invoke(StatefulGuard $guard, $user, #[\SensitiveParameter] array|bool|float|int|string|null $password = null): bool
    {
        if (! is_string($password)) {
            return false;
        }

        if (! $user instanceof User) {
            return (bool) parent::__invoke($guard, $user, $password);
        }

        return PasswordTries::check($user, fn (): bool => (bool) parent::__invoke($guard, $user, $password));
    }
}
