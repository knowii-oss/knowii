<?php

namespace App\Contracts\Auth;

use App\Models\User;
use App\Models\UserIdentity;

/**
 * Remove the connection between a member and an account of an OpenID Connect provider (#1385, ADR 0045)
 */
interface UnlinksIdentities
{
    /**
     * Always allowed for the member who owns the identity: the account keeps its address, so a sign-in link or a
     * password reset still works
     */
    public function unlink(User $user, UserIdentity $identity): void;
}
