<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Contracts\HasAbilities;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The pages of the application accept the session only (ADR 0022): an API token works on the API, with its abilities. The
 * sign-in check of most pages uses the guard of Sanctum, which reads the session first and a token after it, and no page
 * names the abilities that it needs. So a request outside the API whose account comes from a token is refused with a 403
 * (the JSON error of ADR 0011 for a request that expects JSON), whatever the abilities of the token.
 *
 * It runs in the `web` group: every page, the routes of Jetstream, the invite routes, the routes of a change of the email
 * address and the authorization of the live channels. The routes of Fortify are in the group too, but their sign-in check
 * uses the session guard, which never reads a token: a request with only a token is a visitor there (the sign-in page, or
 * 401 for JSON). The priority list (bootstrap/app.php) puts it right after
 * the sign-in check of the route and before the other checks of the route (the limits, the check of the password of the
 * session, the check of the email address), so a refused request uses up no try of the account. A request with a session
 * is not changed, also when it carries a token header: the guard of Sanctum reads the session first, and marks its account
 * with a TransientToken. A request without an account is not changed either: the sign-in check of the route, if any,
 * answers it.
 */
class AcceptOnlyTheSessionOnPages
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        self::check($request);

        return $next($request);
    }

    /**
     * Refuses the request when its account comes from a token
     *
     * @throws AccessDeniedHttpException
     */
    public static function check(Request $request): void
    {
        // The account that the sign-in check of the route found, if any
        $account = $request->user();
        if (! $account instanceof User) {
            return;
        }

        // The account of a session holds the TransientToken of Sanctum, or no token at all (the session guard). Any other
        // token is a credential of the API, despite the PHPDoc of HasApiTokens
        /** @var HasAbilities|null $token */
        $token = $account->currentAccessToken();
        if ($token !== null && ! $token instanceof TransientToken) {
            throw new AccessDeniedHttpException('This address accepts the session of the web application only. An API token works on the API.');
        }
    }
}
