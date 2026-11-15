<?php

namespace App\Http\Middleware;

use App\Support\Robots;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * X-Robots-Tag on every response (#104): noindex on the API, on the pages of a signed-in member, on the whole
 * instance when it does not allow indexing, and on the pages of a community unless a visitor reads them in a public
 * space of a community that allows indexing (#1487). Search engines read it even when they ignore robots.txt.
 */
class AddRobotsHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $response->headers->has('X-Robots-Tag')) {
            // One place decides (Robots::forRequest), the meta tag of the page asks it too. An error on a community page is never indexed
            $directive = $response->isSuccessful() || ! $request->is('community/*') ? Robots::forRequest($request) : Robots::directive(true, false);
            $response->headers->set('X-Robots-Tag', $directive);
        }

        return $response;
    }
}
