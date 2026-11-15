<?php

namespace App\Contracts\Auth;

use App\Exceptions\OpenIdConnectException;
use App\Models\User;
use App\Models\UserIdentity;
use App\Support\OpenIdIdentity;

/**
 * Connect an account of an OpenID Connect provider to the account of a signed-in member (#1385, ADR 0045)
 */
interface LinksIdentities
{
    /**
     * @throws OpenIdConnectException When the provider account belongs to another member, or the member already has
     *                                an account at this provider
     */
    public function link(User $user, OpenIdIdentity $identity): UserIdentity;
}
