<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\UnlinksIdentities;
use App\Contracts\Entitlements\SyncsEntitlementRules;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UserIdentity;
use App\Support\OpenIdConnect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Disconnect an account of an OpenID Connect provider (#1385, ADR 0045). The member keeps the account and its
 * address: a sign-in link or a password reset still works, so this is always allowed. Sessions and API tokens stay. The
 * entitlement keys of the account and the access group memberships that rules gave because of them go at once (#1386).
 */
class UnlinkIdentity implements UnlinksIdentities
{
    public function __construct(private readonly SyncsEntitlementRules $syncs) {}

    final public function unlink(User $user, UserIdentity $identity): void
    {
        // The route never hands over an identity of another member; this is the last check
        abort_unless($identity->user_id === $user->id, 404);

        DB::transaction(function () use ($user, $identity): void {
            $identity->delete();
            // The entitlement keys that this account reported go at once (#1386, ADR 0047)
            UserEntitlement::query()->where('user_id', $user->id)->where('issuer', $identity->issuer)->delete();
        });
        // And the access groups that rules gave because of them (the rules only ever use the issuer of this instance)
        if ($identity->issuer === OpenIdConnect::issuer()) {
            $this->syncs->clearMember($user);
        }

        Log::info('Disconnected an OpenID Connect account', ['user' => $user->cuid, 'issuer' => $identity->issuer, 'identity' => $identity->cuid]);
    }
}
