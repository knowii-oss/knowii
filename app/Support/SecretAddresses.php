<?php

namespace App\Support;

use App\ApiRoutes;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * The addresses that hold a secret (ADR 0064): a link token, a signature, or a one-time code in the query. The session
 * keeps none of them as its previous address (App\Http\Middleware\StartSessionWithoutTokenAddresses), and the statistics
 * never see them (App\Support\Analytics).
 *
 * An address holds a secret when its route is signed, has a token parameter, or is named in the list, or when its path
 * starts like the path of one of these routes, or when the fallback route answers it: every address that no route
 * matches (an address near a link, with a character too many, a path too long or a bent path) answers 404 and is kept
 * nowhere.
 * So a new signed route, or a new route with a parameter of SECRET_PARAMETERS, is covered without a change here.
 *
 * WARNING: tests/Feature/SecretAddressesTest.php lists every parameter name of the routes, of every method: a new name must go in
 * SECRET_PARAMETERS or in the public names of that test, and each route that holds a secret needs its rows there.
 */
final class SecretAddresses
{
    /**
     * The route parameters whose value is a secret: the token of a sign-in link, of a password reset and of an invite link,
     * and the hash of the address of a signed link
     *
     * @var list<string>
     */
    public const array SECRET_PARAMETERS = ['token', 'inviteLinkToken', 'hash'];

    /**
     * The routes whose query holds the secret: the one-time code of the OpenID Connect provider
     *
     * @var list<string>
     */
    public const array SECRET_QUERY_ROUTES = ['oidc.callback'];

    /**
     * The patterns of the paths of these routes: each one matches the full path of its route with any value of each
     * parameter (also a longer path after a parameter), and the whole path of a route of SECRET_QUERY_ROUTES. The guard
     * test checks this for every route that holds a secret. The comparison ignores the case: an address in capital letters
     * reaches no route. `/invite` itself (the page of an invite link) holds no token and does not match
     *
     * @var list<string>
     */
    public const array PATHS = [
        'invite/*',
        ApiRoutes::BASE_PATH.'/invite-links/*',
        'login/link/*',
        'reset-password/*',
        'email/verify/*',
        'user/email-change/*',
        'community-invitations/*',
        'auth/oidc/callback',
    ];

    /**
     * Whether the address of the request holds a secret. Read after the routing: the route of the request decides, the
     * fallback route counts as one (no 404 of an unknown address is kept), and the path covers the addresses that reach no
     * route at all
     */
    public static function holdsASecret(Request $request): bool
    {
        $route = $request->route();

        return ($route instanceof Route && ($route->isFallback || self::routeHoldsASecret($route)))
            || Str::is(self::PATHS, $request->decodedPath(), ignoreCase: true);
    }

    /**
     * Whether the addresses of a route hold a secret: the route is signed, has a parameter of SECRET_PARAMETERS, or is
     * in SECRET_QUERY_ROUTES
     */
    public static function routeHoldsASecret(Route $route): bool
    {
        /** @var list<string> $names */
        $names = $route->parameterNames();

        return array_intersect($names, self::SECRET_PARAMETERS) !== []
            || in_array($route->getName(), self::SECRET_QUERY_ROUTES, true)
            || self::isSigned($route);
    }

    /**
     * Whether the route checks a signature: the middleware `signed` (also `signed:relative`) or a class of it, also through
     * a group. The router resolves the names as it does for the request
     */
    private static function isSigned(Route $route): bool
    {
        foreach (app(Router::class)->gatherRouteMiddleware($route) as $middleware) {
            if (is_string($middleware) && is_a(Str::before($middleware, ':'), ValidateSignature::class, true)) {
                return true;
            }
        }

        return false;
    }
}
