<?php

namespace App\Http\Requests;

use App\Constants;
use App\Models\User;
use App\Support\EndedSession;
use App\Support\Landing;
use Illuminate\Http\Exceptions\HttpResponseException;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest as FortifyTwoFactorLoginRequest;

/**
 * The request of the challenge of the second factor (Fortify). A challenge holds the account in the session from the
 * sign-in on (`login.id`), and the time when it began. When that account has no second factor any more (it was turned off,
 * or a takeover removed it, ADR 0059), or when the challenge is older than Constants::SECOND_FACTOR_CHALLENGE_MINUTES, the
 * challenge ends: the session forgets the account, the choice "remember me" and the landing that a sign-in link held for
 * it, and the person goes back to the sign-in page. The page of the challenge does the same. After a challenge that is too
 * old, the sign-in page says "The sign-in took too long. Sign in again."
 */
class TwoFactorLoginRequest extends FortifyTwoFactorLoginRequest
{
    /**
     * The key of the session that holds the time when the challenge began (a Unix time)
     */
    public const string STARTED_AT_KEY = 'login.started_at';

    /**
     * The value of STARTED_AT_KEY for a challenge that begins now. Every place that begins a challenge (the password, a
     * sign-in link, the provider) stores it with `login.id`
     */
    public static function startedNow(): int
    {
        return now()->getTimestamp();
    }

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
        $secondFactor = $user instanceof User && $user->hasEnabledTwoFactorAuthentication();
        if ($secondFactor && $this->isRecent()) {
            return $user;
        }

        $this->session()->forget(['login.id', 'login.remember', self::STARTED_AT_KEY]);
        Landing::forgetHeld($this);
        if ($secondFactor) {
            // The sign-in page says why: the challenge is too old
            $this->session()->flash(EndedSession::WARNING_KEY, __('The sign-in took too long. Sign in again.'));
        }

        throw new HttpResponseException(redirect()->route('login'));
    }

    /**
     * Whether the challenge began less than Constants::SECOND_FACTOR_CHALLENGE_MINUTES ago
     */
    private function isRecent(): bool
    {
        $startedAt = $this->session()->get(self::STARTED_AT_KEY);

        return is_int($startedAt) && now()->getTimestamp() - $startedAt <= Constants::SECOND_FACTOR_CHALLENGE_MINUTES * 60;
    }
}
