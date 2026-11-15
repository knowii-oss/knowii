<?php

namespace App\Http\Controllers\API;

use App\Contracts\Auth\UnlinksIdentities;
use App\Http\Controllers\Controller;
use App\Models\UserIdentity;
use App\Traits\ApiResponses;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The accounts of OpenID Connect providers that the current member connected (#1385, ADR 0045). The sign-in itself is a
 * browser flow: the API only lists and removes the links. Own rows only: the row of another member gives a 404, and the
 * subject (the identifier at the provider) is never in an answer.
 */
class UserIdentityApiController extends Controller
{
    use ApiResponses;

    /**
     * The provider accounts that you connected: the issuer, a label for it, the date of the link and of its last use.
     *
     * @throws AuthenticationException
     */
    final public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        return self::success('Connected accounts', null, $user->identities()->orderBy('linked_at')->get()->toResourceCollection());
    }

    /**
     * Disconnect a provider account, by identity cuid. You keep your account and its email address: a sign-in link or a
     * password reset still works. Your sessions and API tokens stay.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, string $identityCuid): Response
    {
        $user = $request->user() ?? throw new AuthenticationException;

        app(UnlinksIdentities::class)->unlink($user, UserIdentity::query()->where('user_id', $user->id)->where('cuid', $identityCuid)->firstOrFail());

        return self::deleted();
    }
}
