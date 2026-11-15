<?php

namespace App\Contracts\Auth;

use App\Exceptions\OpenIdConnectException;
use App\Models\User;
use App\Support\OpenIdIdentity;

/**
 * Decide which platform account an OpenID Connect identity signs in (#1385, ADR 0045, rule 5 of the issue)
 */
interface SignsInWithOpenIdConnect
{
    /**
     * @param  User|null  $linking  The signed-in member who started the flow from the profile settings to connect the
     *                              account, or null for a sign-in
     * @return User The account to sign in (or, when linking, the member)
     *
     * @throws OpenIdConnectException When the identity must not sign in: the message tells the member what to do
     */
    public function resolve(OpenIdIdentity $identity, ?User $linking): User;
}
