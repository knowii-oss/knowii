<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserEntitlement;
use App\Support\OidcEntitlements;
use App\Support\OpenIdConnect;
use App\Traits\ApiResponses;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the sign-in provider says that the current member owns (#1386, ADR 0047): the keys that were stored at the last
 * read and the date of that read. Own data only. Without the feature (no `OIDC_ENTITLEMENTS_URL`) the route answers 404.
 */
class UserEntitlementApiController extends Controller
{
    use ApiResponses;

    /**
     * The entitlement keys that the sign-in provider reported for you, and the date of the last check. The list is empty
     * when you did not connect an account of the provider, or the provider has nothing for you. Needs the ability
     * entitlements:read (not in the default set of a new token).
     *
     * @throws AuthenticationException
     */
    final public function index(Request $request): JsonResponse
    {
        abort_unless(OidcEntitlements::enabled(), 404);
        $user = $request->user() ?? throw new AuthenticationException;

        $issuer = OpenIdConnect::issuer() ?? '';
        $identity = $user->identities()->where('issuer', $issuer)->first();
        $keys = $identity === null ? [] : $user->entitlements()->where('issuer', $issuer)->orderBy('key')->get()->map(static fn (UserEntitlement $entitlement): string => $entitlement->key)->values()->all();

        return self::success('Entitlements', null, [
            'entitlements' => $keys,
            'checked_at' => $identity?->entitlements_checked_at?->toIso8601String(),
        ]);
    }
}
