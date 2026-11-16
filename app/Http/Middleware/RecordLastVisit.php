<?php

namespace App\Http\Middleware;

use App\Constants;
use App\Contracts\Users\RecordsLastVisits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The last visit of a signed-in member (#1476, ADR 0048): one date, written at most once a day, for page requests only
 * (a GET that asks for a page, or an Inertia visit), never for the API or the assets. A date that is 7 days old or more
 * waits in the session, for the "Catch up" card.
 */
class RecordLastVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $request->hasSession() && self::isPageRequest($request)) {
            $replaced = app(RecordsLastVisits::class)->record($user);
            if ($replaced !== null) {
                $request->session()->put(Constants::SESSION_CATCH_UP_SINCE, $replaced);
            }
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    private static function isPageRequest(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->headers->get('Purpose') === 'prefetch') {
            return false;
        }

        return $request->headers->has('X-Inertia') || ! $request->expectsJson();
    }
}
