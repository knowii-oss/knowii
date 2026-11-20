<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Landing;
use Illuminate\Http\Exceptions\HttpResponseException;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest as FortifyTwoFactorLoginRequest;

/**
 * The request of the challenge of the second factor (Fortify). A challenge holds the account in the session from the
 * sign-in on (`login.id`). When that account has no second factor any more (it was turned off, or a takeover removed it,
 * ADR 0059), the challenge ends: the session forgets the account, the choice "remember me" and the landing that a sign-in
 * link held for it, and the person goes back to the sign-in page. The page of the challenge does the same.
 */
class TwoFactorLoginRequest extends FortifyTwoFactorLoginRequest
{
    /**
     * @return bool
     */
    public function hasChallengedUser()
    {
        if (! parent::hasChallengedUser()) {
            return false;
        }

        try {
            $this->challengedUser();
        } catch (HttpResponseException) {
            return false;
        }

        return true;
    }

    /**
     * @return User
     *
     * @throws HttpResponseException
     */
    public function challengedUser()
    {
        $user = parent::challengedUser();
        if ($user instanceof User && $user->hasEnabledTwoFactorAuthentication()) {
            return $user;
        }

        $this->session()->forget(['login.id', 'login.remember']);
        Landing::forgetHeld($this);

        throw new HttpResponseException(redirect()->route('login'));
    }
}
