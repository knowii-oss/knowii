<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * The token abilities of an API route (ADR 0022, #689): a request with a personal access token passes when the token
 * has any of the given abilities. Members signed in through the web session (first party) are not limited by
 * abilities, like Sanctum's TransientToken. A visitor (no session, no token) passes only on a route that accepts
 * visitors (#1387).
 */
class RequireTokenAbility
{
    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthenticationException
     * @throws MissingAbilityException
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();
        if ($user === null) {
            // A visitor on a route that accepts visitors (AuthenticateIfPresent) has no token: nothing to check. Any
            // other request without a user is refused
            if ($request->attributes->get(AuthenticateIfPresent::VISITOR_ATTRIBUTE) === true) {
                return $next($request);
            }

            throw new AuthenticationException;
        }

        // A TransientToken (web session) or none in tests, despite the PHPDoc of HasApiTokens
        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            foreach ($abilities as $ability) {
                if ($token->can($ability)) {
                    return $next($request);
                }
            }

            throw new MissingAbilityException($abilities);
        }

        return $next($request);
    }
}
