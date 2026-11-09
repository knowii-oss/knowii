<?php

namespace App\Http\Middleware;

use App\ApiRoutes;
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
        if (in_array($request->getRealMethod(), ['POST', 'PUT', 'PATCH'], true) && ! $request->isJson() && ! self::acceptsFiles($request)) {
            return self::unsupportedMediaTypeIssue();
        }

        return $next($request);
    }

    /**
     * File uploads are the only non-JSON bodies the API accepts, on the upload endpoints only (#1178).
     */
    private static function acceptsFiles(Request $request): bool
    {
        $isMultipart = str_starts_with(strtolower((string) $request->header('Content-Type')), 'multipart/form-data')
            || $request->allFiles() !== [];

        return $isMultipart && $request->is(str_replace('{'.ApiRoutes::PARAM_COMMUNITY.'}', '*', ApiRoutes::path(ApiRoutes::COMMUNITY_UPLOADS)));
    }
}
