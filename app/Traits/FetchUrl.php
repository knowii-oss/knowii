<?php

namespace App\Traits;

use App\Constants;
use App\Exceptions\BusinessException;
use App\Exceptions\TechnicalException;
use App\Support\SafeUrl;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

trait FetchUrl
{
    /**
     * Optional Guzzle handler stack. When set, it overrides the default
     * handler so the HTTP layer can be faked (e.g. with a MockHandler) in
     * tests. In production it stays null and the default client is used.
     */
    protected ?HandlerStack $httpHandlerStack = null;

    /**
     * Override the Guzzle handler stack used by {@see self::getClient()}.
     */
    public function setHttpHandlerStack(?HandlerStack $handlerStack): void
    {
        $this->httpHandlerStack = $handlerStack;
    }

    /**
     * Request options for an address given by a member or by a fetched page (#1231): the URL and every redirect
     * must point to a public address, the host is pinned to the checked address, and the body size is limited.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws BusinessException When the URL must not be fetched
     */
    private function externalRequestOptions(string $url, array $options = []): array
    {
        $address = SafeUrl::assertPublic($url);

        /** @var array<string, mixed> $redirects */
        $redirects = is_array($options['allow_redirects'] ?? null) ? $options['allow_redirects'] : [];

        return [
            ...$options,
            'curl' => SafeUrl::pinnedResolveOption($url, $address),
            'allow_redirects' => [
                'max' => 5,
                'protocols' => ['http', 'https'],
                ...$redirects,
                // A redirect to an internal address is refused like the first URL
                'on_redirect' => static function (RequestInterface $request, ResponseInterface $response, UriInterface $uri): void {
                    SafeUrl::assertPublic((string) $uri);
                },
            ],
            // Stop large downloads (curl only)
            'progress' => static function (int $downloadTotal, int $downloaded): void {
                if ($downloadTotal > Constants::MAX_FETCH_SIZE_BYTES || $downloaded > Constants::MAX_FETCH_SIZE_BYTES) {
                    throw new BusinessException('The page or the image is too large');
                }
            },
        ];
    }

    /**
     * Fetch the content of a URL using Browserless.
     *
     * @throws Exception
     */
    final public function fetchUrl(string $url): string
    {
        // Browserless is trusted (configuration), the page it loads is not
        SafeUrl::assertPublic($url);

        $client = $this->getClient();

        try {

            // References
            // https://docs.browserless.io/http-apis/content
            // Alternative: https://docs.browserless.io/http-apis/scrape
            /** @var string|null $browserlessUrl */
            $browserlessUrl = config('browserless.url');
            /** @var string|null $browserlessToken */
            $browserlessToken = config('browserless.token');

            if (empty($browserlessUrl) || empty($browserlessToken)) {
                throw new TechnicalException('Browserless URL or token is not configured correctly.');
            }

            // Assert these are strings
            $browserlessUrl = (string) $browserlessUrl;
            $browserlessToken = (string) $browserlessToken;

            $browserlessUrl .= Constants::BROWSERLESS_CONTENT_API_PATH.'?'.Constants::BROWSERLESS_TOKEN_API_PARAMETER.'='.$browserlessToken.'&blockAds=true&launch=%7B%20%22stealth%22%3A%20true%2C%20%22headless%22%3A%20false%20%7D';

            $response = $client->post($browserlessUrl, [
                'json' => [
                    'url' => $url,
                    'bestAttempt' => true,

                    // Accelerate loading by skipping some resources
                    // Reference
                    // https://docs.browserless.io/http-apis/content#rejecting-undesired-requests
                    'rejectRequestPattern' => ['/^.*\\.(css|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot|webp|avif|apng)$/i'],
                    'rejectResourceTypes' => ['stylesheet', 'font', 'image', 'media'],

                    'gotoOptions' => [
                        'timeout' => 10000,
                        'waitUntil' => 'networkidle2',
                        'referer' => config('app.url'), // announce ourselves
                    ],
                ],
            ]);

            // With /scrape:
            // "element": [{ "selector": "html" }],

            $html = $response->getBody()->getContents();

            if ($html === '' || $response->getStatusCode() !== 200) {
                throw new Exception('Failed to fetch the page');
            }

            return $html;
        } catch (GuzzleException $e) {
            Log::debug('Error while loading the page: ', [$e]);
            throw new Exception('Failed to fetch the page', 0, $e);
        }
    }

    /**
     * Follow redirects and get the final URL.
     *
     * @throws GuzzleException
     */
    final public function getFinalUrlAfterRedirects(string $url): string
    {
        $client = $this->getClient();
        $response = $client->get($url, $this->externalRequestOptions($url, ['allow_redirects' => ['track_redirects' => true]]));

        $redirects = $response->getHeader('X-Guzzle-Redirect-History');

        return empty($redirects) ? $url : end($redirects);
    }

    /**
     * Check the availability of a URL and return detailed status.
     */
    final public function isUrlAvailable(string $url): bool
    {
        $client = $this->getClient();
        try {
            $response = $client->head($url, $this->externalRequestOptions($url, [
                // Don't throw exceptions on 4xx and 5xx status codes
                // Reference: https://docs.guzzlephp.org/en/stable/request-options.html#http-errors
                'http_errors' => false,
            ]));
            $statusCode = $response->getStatusCode();

            Log::debug('URL status: '.$statusCode.' for '.$url);

            // Some servers do not support HEAD requests
            if ($statusCode === 405) {
                Log::debug('HEAD requests not allowed. Trying a GET request: '.$statusCode.' for '.$url);
                $response = $client->get($url, $this->externalRequestOptions($url));
                $statusCode = $response->getStatusCode();
            }

            return $statusCode >= 200 && $statusCode < 300;
        } catch (\RuntimeException $e) {
            Log::debug('Error while checking the URL: '.$e->getMessage());

            return false;
        } catch (GuzzleException $e) {
            Log::debug('Error while checking the URL: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Download an image from an external address (cover image, icon of the site). Null when the address is refused,
     * unavailable or not an image: images are a bonus, they never stop a capture (#1063).
     */
    final public function downloadImage(string $url): ?string
    {
        try {
            $response = $this->getClient()->get($url, $this->externalRequestOptions($url, ['http_errors' => false]));
            $isImage = str_starts_with(strtolower($response->getHeaderLine('Content-Type')), 'image/');

            if ($response->getStatusCode() !== 200 || ! $isImage) {
                return null;
            }

            $bytes = $response->getBody()->getContents();

            return $bytes === '' || strlen($bytes) > Constants::MAX_FETCH_SIZE_BYTES ? null : $bytes;
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
            'allow_redirects' => true,
            'cookies' => true,
            'timeout' => 10, // seconds
            // Check the TLS certificates (#1231)
            'verify' => true,
        ];

        if ($this->httpHandlerStack !== null) {
            $config['handler'] = $this->httpHandlerStack;
        }

        return new Client($config);
    }
}
