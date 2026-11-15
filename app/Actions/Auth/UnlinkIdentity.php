<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\UnlinksIdentities;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\Log;

/**
 * Disconnect an account of an OpenID Connect provider (#1385, ADR 0045). The member keeps the account and its
 * address: a sign-in link or a password reset still works, so this is always allowed. Sessions and API tokens stay.
 */
class UnlinkIdentity implements UnlinksIdentities
{
    final public function unlink(User $user, UserIdentity $identity): void
    {
        // The route never hands over an identity of another member; this is the last check
        abort_unless($identity->user_id === $user->id, 404);

        $identity->delete();

        Log::info('Disconnected an OpenID Connect account', ['user' => $user->cuid, 'issuer' => $identity->issuer, 'identity' => $identity->cuid]);
    }
}
