<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponses;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure that API requests sending input use JSON.
 *
 * Write requests (POST/PUT/PATCH) must use the `application/json` content type.
 * Bodyless methods (GET/HEAD/DELETE/OPTIONS) are left untouched.
 */
class EnsureRequestIsJson
{
    use ApiResponses;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getRealMethod(), ['POST', 'PUT', 'PATCH'], true) && ! $request->isJson()) {
            return self::unsupportedMediaTypeIssue();
        }

        return $next($request);
    }
}
