<?php

namespace App\Http\Middleware;

use App\Support\Robots;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * X-Robots-Tag on every response (#104): noindex on the API, on the pages of a signed-in member, and on the whole
 * instance when it does not allow indexing. Search engines read it even when they ignore robots.txt.
 */
class AddRobotsHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $response->headers->has('X-Robots-Tag')) {
            $directive = Robots::directive($request->user() !== null, $request->is('api/*'));
            $response->headers->set('X-Robots-Tag', $directive);
        }

        return $response;
    }
}
