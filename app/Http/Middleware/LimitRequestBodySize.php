<?php

namespace App\Http\Middleware;

use App\Constants;
use App\Enums\KnowiiApiResponseCategory;
use App\Enums\KnowiiApiResponseType;
use App\Http\Controllers\API\KnowiiApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A request body is limited to Constants::MAX_REQUEST_BODY_BYTES (ADR 0073), except a form with files on the upload
 * routes of the API ({@see EnsureRequestIsJson::isUploadRoute()}), which check their files themselves. A body that
 * announces no length but comes in chunks is refused (411).
 *
 * Two places check it: public/index.php, before the framework reads or decodes the body
 * ({@see self::answerBeforeTheFramework()}), then this global middleware, before the session, as a second net.
 */
class LimitRequestBodySize
{
    // The size of one read of a body without a length
    private const int READ_BYTES = 65536;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $status = self::refusal(
            $request->getRealMethod(),
            $request->decodedPath(),
            $request->headers->get('Content-Type'),
            $request->headers->get('Content-Length'),
            $request->headers->has('Transfer-Encoding'),
            static fn (): int => strlen((string) $request->getContent()),
        );
        if ($status !== null) {
            throw new HttpException($status);
        }

        return $next($request);
    }

    /**
     * The answer that refuses the body of a request, before the framework reads or decodes it (public/index.php), or null
     * when the request goes on. The length that the request announces decides; a body without a length is read only up to
     * the limit (the server keeps it for the framework, which reads it again).
     *
     * @param  array<array-key, scalar|array<array-key, scalar>|null>  $server  The values of the server ($_SERVER)
     * @param  Closure(): (resource|false)  $body  The body as a stream, opened only when the request announces no length
     * @return array{status: int, headers: array<string, string>, body: string}|null
     */
    public static function answerBeforeTheFramework(array $server, Closure $body): ?array
    {
        $value = static fn (string $name): ?string => is_string($server[$name] ?? null) ? $server[$name] : null;
        $path = parse_url($value('REQUEST_URI') ?? '/', PHP_URL_PATH);
        $path = trim(rawurldecode(is_string($path) ? $path : '/'), '/');

        $status = self::refusal(
            strtoupper($value('REQUEST_METHOD') ?? 'GET'),
            $path === '' ? '/' : $path,
            $value('CONTENT_TYPE') ?? $value('HTTP_CONTENT_TYPE'),
            $value('CONTENT_LENGTH') ?? $value('HTTP_CONTENT_LENGTH'),
            $value('HTTP_TRANSFER_ENCODING') !== null,
            static function () use ($body): int {
                $stream = $body();
                if (! is_resource($stream)) {
                    return 0;
                }
                $read = 0;
                while ($read <= Constants::MAX_REQUEST_BODY_BYTES) {
                    $chunk = fread($stream, self::READ_BYTES);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    $read += strlen($chunk);
                }

                return $read;
            },
        );
        if ($status === null) {
            return null;
        }

        $message = Response::$statusTexts[$status] ?? 'HTTP error';
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store'];
        // The answer of the API (ADR 0011) for the API and for a client that asks for JSON, plain text for the others
        if (str_starts_with($path, 'api/') || str_contains(strtolower($value('HTTP_ACCEPT') ?? ''), 'json')) {
            $answer = new KnowiiApiResponse(KnowiiApiResponseCategory::Technical, KnowiiApiResponseType::HttpIssue, $message);

            return ['status' => $status, 'headers' => ['Content-Type' => 'application/json'] + $headers, 'body' => (string) json_encode($answer)];
        }

        return ['status' => $status, 'headers' => ['Content-Type' => 'text/plain; charset=UTF-8'] + $headers, 'body' => $message];
    }

    /**
     * 413 for a body over the limit, 411 for a body in chunks without a length, null when the request goes on
     *
     * @param  Closure(): int  $bodySize  The size of a body without a length, read at most a little over the limit
     */
    private static function refusal(string $method, string $path, ?string $contentType, ?string $length, bool $chunked, Closure $bodySize): ?int
    {
        // A form with files on an upload route: the route checks its files (PHP limits the body with post_max_size)
        if ($method === 'POST' && EnsureRequestIsJson::isMultipart($contentType) && EnsureRequestIsJson::isUploadPath($path)) {
            return null;
        }

        if ($length !== null && ctype_digit($length)) {
            return (int) $length > Constants::MAX_REQUEST_BODY_BYTES ? Response::HTTP_REQUEST_ENTITY_TOO_LARGE : null;
        }

        if ($chunked) {
            return Response::HTTP_LENGTH_REQUIRED;
        }

        return $bodySize() > Constants::MAX_REQUEST_BODY_BYTES ? Response::HTTP_REQUEST_ENTITY_TOO_LARGE : null;
    }
}
