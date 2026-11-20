<?php

namespace App\Listeners;

use App\Support\Landing;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

/**
 * A sign-in with a password starts the challenge of the second factor (#1612, ADR 0057): it replaces an earlier challenge
 * of the same session, so the landing that a sign-in link left for that one is removed. The answer of a sign-in with a
 * password then stays the one of before.
 */
class ForgetHeldLanding
{
    final public function handle(TwoFactorAuthenticationChallenged $event): void
    {
        Landing::forgetHeld(request());
    }
}
