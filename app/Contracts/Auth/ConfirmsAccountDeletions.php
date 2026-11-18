<?php

namespace App\Contracts\Auth;

use App\Exceptions\OpenIdConnectException;
use App\Models\User;
use App\Support\OpenIdIdentity;
use Carbon\CarbonImmutable;

/**
 * Record the proof that an account without a password wants to be deleted: a new sign-in at the OpenID Connect provider
 * of the instance (#1521, ADR 0052). It never deletes anything.
 */
interface ConfirmsAccountDeletions
{
    /**
     * @param  User  $member  The signed-in member who started the proof
     * @param  OpenIdIdentity  $identity  The identity of the ID token, after every check of the client
     * @param  int  $flowStartedAt  When the member started this proof (a Unix time): the sign-in must come after it
     * @return CarbonImmutable Until when the proof holds
     *
     * @throws OpenIdConnectException When the sign-in does not prove the intent: the message tells the member why
     */
    public function confirm(User $member, OpenIdIdentity $identity, int $flowStartedAt): CarbonImmutable;
}
