<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The routes that visitors may read (#1387, ADR 0044): the web pages and the API reads of the public spaces.
 *
 * Without credentials the request goes on as a visitor: no user, and the policies decide (SpacePolicy::view). With a
 * session or a token, it is exactly `auth:sanctum` followed by `verified`: the same guard, and an unverified account
 * is refused like everywhere else. A token that Sanctum refuses (unknown, revoked, expired) gets a 401, it never
 * silently becomes a visitor. The abilities of a token are still checked by the `ability:` middleware of the route,
 * which lets only a request marked as a visitor go without a user.
 */
class AuthenticateIfPresent implements AuthenticatesRequests
{
    /**
     * Request attribute set when nobody is signed in: ability:* then has no token to check
     */
    public const string VISITOR_ATTRIBUTE = 'knowii.visitor';

    public function __construct(private readonly AuthFactory $auth) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->auth->guard('sanctum');

        if ($guard->check()) {
            $this->auth->shouldUse('sanctum');

            // An unverified account is refused, on the web (redirect) and on the API (403), like `verified`
            /** @var Response */
            return app(EnsureEmailIsVerified::class)->handle($request, $next);
        }

        // A token that Sanctum did not accept is an error, not a visitor
        if ($request->bearerToken() !== null) {
            throw new AuthenticationException('Unauthenticated.', ['sanctum']);
        }

        $request->attributes->set(self::VISITOR_ATTRIBUTE, true);

        // After a click on "Sign in" the member comes back to the page that they were reading
        if ($request->isMethod('GET') && ! $request->is('api/*') && $request->hasSession()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return $next($request);
    }
}
