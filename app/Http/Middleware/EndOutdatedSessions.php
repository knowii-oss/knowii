<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A session holds the version of the sessions of its account from its sign-in (ADR 0059, App\Listeners\StoreSessionVersion).
 * A takeover of the account adds 1 to the version of the account, so a session of before holds another number: this
 * middleware signs it out at its next request, invalidates it, and answers as for an expired session (a redirect to the
 * sign-in page, 401 for a JSON request). So a takeover ends every session of the account, whatever the session driver.
 *
 * It runs in the `web` group (every page, the routes of Fortify and Jetstream, the authorization of the broadcast
 * channels) and in the `api` group, where only a request of the first-party front-end has a session. A session without
 * a value counts as 0, the version of every account that was never taken over.
 */
class EndOutdatedSessions
{
    /**
     * Where the session keeps the version of the sessions of its account
     */
    public const string SESSION_KEY = 'session_version';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return self::next($request, $next);
        }

        // The sign-in by the remember cookie happens here, if any: it stores the version of now
        $guard = Auth::guard('web');
        $user = $guard->user();
        if (! $user instanceof User) {
            return self::next($request, $next);
        }

        $version = $request->session()->get(self::SESSION_KEY, 0);
        if (is_numeric($version) && (int) $version === $user->sessionVersion()) {
            return self::next($request, $next);
        }

        $guard->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        throw new AuthenticationException('Unauthenticated.', ['web']);
    }

    private static function next(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
