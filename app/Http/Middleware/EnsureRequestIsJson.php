<?php

namespace App\Http\Middleware;

use App\ApiRoutes;
use App\Traits\ApiResponses;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
     * File uploads are the only non-JSON bodies the API accepts, on the upload endpoints only (#1178, #109, #1390).
     */
    private static function acceptsFiles(Request $request): bool
    {
        $isMultipart = self::isMultipart($request->header('Content-Type')) || $request->allFiles() !== [];

        return $isMultipart && self::isUploadRoute($request);
    }

    /**
     * The routes that take a file (#1178, #109, #1336, #1390): the only routes whose body may pass
     * Constants::MAX_REQUEST_BODY_BYTES (ADR 0073), as a form with files only. They check the size of their files themselves
     */
    public static function isUploadRoute(Request $request): bool
    {
        return $request->isMethod('POST') && self::isUploadPath($request->decodedPath());
    }

    /**
     * The paths of the upload routes ({@see self::isUploadRoute()}), without the slash at the start. Also read before the
     * framework starts (public/index.php, {@see LimitRequestBodySize::answerBeforeTheFramework()}): plain functions only
     */
    public static function isUploadPath(string $path): bool
    {
        $patterns = [
            str_replace('{'.ApiRoutes::PARAM_COMMUNITY.'}', '*', ApiRoutes::path(ApiRoutes::COMMUNITY_UPLOADS)),
            // A file of a course material (#1336)
            str_replace(['{'.ApiRoutes::PARAM_COMMUNITY.'}', '{'.ApiRoutes::PARAM_SPACE.'}'], '*', ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_MATERIALS)),
            // The avatar of the current user (#109)
            ApiRoutes::path(ApiRoutes::USER_AVATAR),
            // The CSV file of an import of invitations (#1390)
            str_replace('{'.ApiRoutes::PARAM_COMMUNITY.'}', '*', ApiRoutes::path(ApiRoutes::COMMUNITY_INVITATION_IMPORTS)),
        ];

        return Str::is($patterns, $path);
    }

    /**
     * A form with files (`multipart/form-data`), from the Content-Type header
     */
    public static function isMultipart(?string $contentType): bool
    {
        return str_starts_with(strtolower(trim((string) $contentType)), 'multipart/form-data');
    }
}
