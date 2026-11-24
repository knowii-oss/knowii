<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\EndedSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A session holds the version of the sessions of its account from its sign-in (ADR 0059, App\Listeners\StoreSessionVersion).
 * A change that must sign the other devices out adds 1 to the version of the account (ADR 0066, App\Contracts\Auth\
 * EndsOtherSessions): a takeover, a change or a reset of the password, "Log out other browser sessions", and the second
 * factor when it is confirmed, turned off, or when its recovery codes are made again. A session of before then holds
 * another number: this middleware ends it at its next request (App\Support\EndedSession), and answers as for an expired
 * session (a redirect to the sign-in page, 401 for a JSON request). So these changes end every other session of the
 * account, on every route and whatever the session driver. The session that made the change stores the new version and
 * goes on.
 *
 * It runs in the `web` group (every page, the routes of Fortify and Jetstream, the authorization of the live channels)
 * and in the `api` group, where only a request of the first-party front-end has a session: every route where a session
 * signs in, before the sign-in check of the route (tests/Feature/OtherSessionsEndTest.php walks the routes). On the API,
 * the check of the password of the session (Sanctum) comes before it, and answers a session whose password changed with the
 * same 401. A session without a value counts as 0, the version of every account whose sessions were never ended.
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

        throw EndedSession::end($request);
    }

    private static function next(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
