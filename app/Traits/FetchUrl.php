<?php

namespace App\Traits;

use App\Constants;
use App\Exceptions\TechnicalException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

trait FetchUrl
{
  /**
   * Fetch the content of a URL using Browserless.
   *
   * @param string $url
   * @return string
   * @throws Exception
   */
  final public function fetchUrl(string $url): string
  {
    $client = $this->getClient();

    try {

      // References
      // https://docs.browserless.io/http-apis/content
      // Alternative: https://docs.browserless.io/http-apis/scrape
      $browserlessUrl = env('BROWSERLESS_URL');
      $browserlessToken = env('BROWSERLESS_TOKEN');

      if (empty($browserlessUrl) || empty($browserlessToken)) {
        throw new TechnicalException('Browserless URL or token is not configured correctly.');
      }

      $browserlessUrl .= Constants::$BROWSERLESS_CONTENT_API_PATH . "?" . Constants::$BROWSERLESS_TOKEN_API_PARAMETER . "=" . $browserlessToken;

      $response = $client->post($browserlessUrl, [
        'json' => [
          "url" => $url,
          "bestAttempt" => true,

          // Accelerate loading by skipping some resources
          // Reference
          // https://docs.browserless.io/http-apis/content#rejecting-undesired-requests
          "rejectRequestPattern" => ["/^.*\\.(css|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot|webp|avif|apng)$/i"],
          "rejectResourceTypes" => ["stylesheet", "font", "image"],

          "gotoOptions" => [
            "timeout" => 10000,
            "waitUntil" => "networkidle2",
            "referer" => env('APP_URL'), // announce ourselves
          ]
        ],
      ]);

      // With /scrape:
      // "element": [{ "selector": "html" }],

      $html = $response->getBody()->getContents();

      if ($html === "" || $response->getStatusCode() !== 200) {
        throw new Exception("Failed to fetch the page");
      }

      return $html;
    } catch (GuzzleException $e) {
      Log::debug("Error while loading the page: ", [$e]);
      throw new Exception("Failed to fetch the page", 0, $e);
    }
  }

  /**
   * Follow redirects and get the final URL.
   *
   * @param string $url
   * @return string
   * @throws GuzzleException
   */
  final public function getFinalUrlAfterRedirects(string $url): string
  {
    $client = $this->getClient();
    $response = $client->get($url, ['allow_redirects' => ['track_redirects' => true]]);

    $redirects = $response->getHeader('X-Guzzle-Redirect-History');
    return empty($redirects) ? $url : end($redirects);
  }

  /**
   * Check the availability of a URL and return detailed status.
   *
   * @param string $url
   * @return bool
   */
  final public function isUrlAvailable(string $url): bool
  {
    $client = $this->getClient();
    try {
      $response = $client->head($url);
      $statusCode = $response->getStatusCode();

      return $statusCode >= 200 && $statusCode < 300;
    } catch (\RuntimeException $e) {
      return false;
    } catch (GuzzleException $e) {
      return false;
    }
  }

  /**
   * Get a configured Guzzle client.
   *
   * @return Client
   */
  final public function getClient(): Client
  {
    return new Client([
      'headers' => [
        'User-Agent' => 'Knowii / 1.0 Crawler',
      ],
      'allow_redirects' => true,
      'cookies' => true,
      'timeout' => 10, // seconds
      'verify' => false, // Ignore SSL certificates
    ]);
  }
}
