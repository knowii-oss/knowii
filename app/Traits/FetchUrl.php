<?php

namespace App\Traits;

use App\Constants;
use App\Exceptions\BusinessException;
use App\Support\SafeUrl;
use App\Support\SizeLimitedStream;
use Carbon\CarbonImmutable;
use CurlHandle;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

trait FetchUrl
{
    /**
     * Timeout of a request that sets none (an image), in seconds
     */
    private const int FETCH_TIMEOUT_SECONDS = 10;

    /**
     * Optional Guzzle handler stack. When set, it overrides the default
     * handler so the HTTP layer can be faked (e.g. with a MockHandler) in
     * tests. In production it stays null and the default client is used.
     */
    protected ?HandlerStack $httpHandlerStack = null;

    /**
     * The end of the time of {@see self::fetchWithin()}, or null: each request has only its own timeout
     */
    private ?CarbonImmutable $fetchDeadline = null;

    /**
     * Override the Guzzle handler stack used by {@see self::getClient()}.
     */
    public function setHttpHandlerStack(?HandlerStack $handlerStack): void
    {
        $this->httpHandlerStack = $handlerStack;
    }

    /**
     * Request options for one address given by a member or by a fetched page (#1231): the address must be public, the
     * host and port are pinned to the checked address, curl refuses a connection to any other address, and the client
     * follows no redirect by itself ({@see self::send()} checks and pins each one).
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws BusinessException When the URL must not be fetched
     */
    private function externalRequestOptions(string $url, array $options = []): array
    {
        $address = SafeUrl::assertPublic($url);

        return [
            ...$options,
            'curl' => SafeUrl::pinnedResolveOption($url, $address) + [
                // A second net: the address that curl really connects to must be public too
                CURLOPT_PREREQFUNCTION => static fn (CurlHandle $handle, string $primaryIp): int => SafeUrl::isPublicAddress($primaryIp) ? CURL_PREREQFUNC_OK : CURL_PREREQFUNC_ABORT,
            ],
            'allow_redirects' => false,
        ];
    }

    /**
     * From now on, every fetch of this object must end within this time (the images of one capture, ADR 0018): a
     * request gets at most the time that is left, and a request after the end is refused. Null: no shared time.
     */
    final public function fetchWithin(?int $seconds): void
    {
        $this->fetchDeadline = $seconds === null ? null : CarbonImmutable::now()->addSeconds($seconds);
    }

    /**
     * The timeout of the next request: its own one, or less when the time of {@see self::fetchWithin()} is nearly used
     *
     * @throws BusinessException When that time is used
     */
    private function timeLeft(float $timeout): float
    {
        if ($this->fetchDeadline === null) {
            return $timeout;
        }

        $left = CarbonImmutable::now()->diffInMilliseconds($this->fetchDeadline, false) / 1000;
        if ($left <= 0) {
            throw new BusinessException('The capture took too long');
        }

        return min($timeout, $left);
    }

    /**
     * Send a GET or HEAD request and follow at most CAPTURE_MAX_REDIRECTS redirects. Each address is checked and
     * pinned before its request: a redirect to another host, port or scheme gets its own check and its own pin.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws BusinessException When an address must not be fetched, or when the page redirects too many times
     * @throws GuzzleException When the site cannot be reached
     */
    private function send(string $method, string $url, array $options = []): ResponseInterface
    {
        // One client for every hop: a cookie set before a redirect is sent to the next address, as a browser does
        $client = $this->getClient();

        for ($redirects = 0; ; $redirects++) {
            $timeout = $this->timeLeft(is_numeric($options['timeout'] ?? null) ? (float) $options['timeout'] : self::FETCH_TIMEOUT_SECONDS);
            // The decoded body, with a limit: a compressed answer cannot pass it
            $sink = new SizeLimitedStream(Constants::MAX_FETCH_SIZE_BYTES);
            try {
                $response = $client->request($method, $url, $this->externalRequestOptions($url, [
                    ...$options,
                    'timeout' => $timeout,
                    'connect_timeout' => min(Constants::CAPTURE_CONNECT_TIMEOUT_SECONDS, $timeout),
                    'sink' => $sink,
                ]));
            } catch (TransferException $e) {
                if ($sink->isTooLarge()) {
                    throw new BusinessException('The page or the image is too large', 0, $e);
                }
                if ($e instanceof RequestException || $e instanceof ConnectException) {
                    if (($e->getHandlerContext()['errno'] ?? null) === CURLE_OPERATION_TIMEDOUT) {
                        throw new BusinessException('The page took too long to load', 0, $e);
                    }
                }
                throw $e;
            }
            if ($sink->isTooLarge()) {
                throw new BusinessException('The page or the image is too large');
            }
            // The body is what the client wrote in the sink
            $sink->rewind();
            $response = $response->withBody($sink);

            $location = $response->getHeaderLine('Location');
            if (! in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true) || $location === '') {
                return $response;
            }
            if ($redirects >= Constants::CAPTURE_MAX_REDIRECTS) {
                throw new BusinessException('The page redirects too many times');
            }

            $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
        }
    }

    /**
     * Fetch a page with a plain HTTP request (ADR 0018: no headless browser). The URL and every redirect must point
     * to a public address, and the body is limited to MAX_FETCH_SIZE_BYTES. 4xx and 5xx answers do not throw: the
     * caller reads the status.
     *
     * @return array{status: int, content_type: string, body: string}
     *
     * @throws BusinessException When an address must not be fetched, or when the page is too large
     * @throws GuzzleException When the site cannot be reached
     */
    final public function fetchPage(string $url): array
    {
        $response = $this->send('GET', $url, [
            'http_errors' => false,
            'timeout' => Constants::CAPTURE_TIMEOUT_SECONDS,
            'headers' => [
                // Sites refuse unknown clients: a browser-like agent that still names Knowii
                'User-Agent' => Constants::CAPTURE_USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            ],
        ]);

        return [
            'status' => $response->getStatusCode(),
            'content_type' => strtolower($response->getHeaderLine('Content-Type')),
            'body' => $response->getBody()->getContents(),
        ];
    }

    /**
     * Download an image from an external address (cover image, icon of the site). Null when the address is refused,
     * unavailable or not an image: images are a bonus, they never stop a capture (#1063).
     */
    final public function downloadImage(string $url): ?string
    {
        try {
            $response = $this->send('GET', $url, ['http_errors' => false]);
            $isImage = str_starts_with(strtolower($response->getHeaderLine('Content-Type')), 'image/');

            if ($response->getStatusCode() !== 200 || ! $isImage) {
                return null;
            }

            $bytes = $response->getBody()->getContents();

            return $bytes === '' ? null : $bytes;
        } catch (Exception $e) {
            Log::debug('Image not downloaded', ['url' => $url, 'reason' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Get a configured Guzzle client.
     */
    final public function getClient(): Client
    {
        $config = [
            'headers' => [
                'User-Agent' => 'Knowii / 1.0 Crawler',
            ],
            // FetchUrl::send() follows the redirects: each one is checked and pinned
            'allow_redirects' => false,
            'cookies' => true,
            // Check the TLS certificates (#1231)
            'verify' => true,
        ];

        if ($this->httpHandlerStack !== null) {
            $config['handler'] = $this->httpHandlerStack;
        }

        return new Client($config);
    }
}
