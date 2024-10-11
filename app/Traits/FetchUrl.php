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
          // Block ads
          // Reference: https://docs.browserless.io/chrome-flags#blocking-ads
          "blockAds" => true,
          "launch" => [
            // Use the stealth mode
            // Reference: https://docs.browserless.io/chrome-flags#stealth-mode
            "stealth" => true,
            // Disable the headless mode to avoid some detections
            // Reference: https://docs.browserless.io/chrome-flags#headful-mode
            "headless" => false,
          ],

          // Accelerate loading by skipping some resources
          // Reference
          // https://docs.browserless.io/http-apis/content#rejecting-undesired-requests
          "rejectRequestPattern" => ["/^.*\\.(css|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot|webp|avif|apng)$/i"],
          "rejectResourceTypes" => ["stylesheet", "font", "image", "media"],

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

      Log::debug("URL status: " . $statusCode . " for " . $url);

      if($statusCode === 405) {
        // Some servers do not support HEAD requests
        $response = $client->get($url);
        $statusCode = $response->getStatusCode();
      }

      return $statusCode >= 200 && $statusCode < 300;
    } catch (\RuntimeException $e) {
      Log::debug("Error while checking the URL: " . $e->getMessage());
      return false;
    } catch (GuzzleException $e) {
      Log::debug("Error while checking the URL: " . $e->getMessage());
      return false;
    }
  }

  /**
 * Check if the given URL is available and points to an image.
 *
 * @param string $url The URL to check
 * @return bool True if the URL is available and points to an image, false otherwise
 */
final public function isUrlAvailableAndIsAnImage(string $url): bool
{
    $client = $this->getClient();
    try {
        $response = $client->head($url);
        $statusCode = $response->getStatusCode();
        $contentType = $response->getHeaderLine('Content-Type');

        return $statusCode >= 200 && $statusCode < 300 && str_starts_with($contentType, 'image/');
    } catch (\RuntimeException | GuzzleException $e) {
        return false;
    }
}

  /**
 * Load an image from a URL and return it as a base64 encoded string.
 *
 * @param string $url The URL of the image file
 * @return string Base64 encoded image data
 * @throws Exception If the image cannot be loaded or encoded
 */
final public function loadImageAsBase64(string $url): string
{
    $client = $this->getClient();

    try {
        $response = $client->get($url);

        if ($response->getStatusCode() !== 200) {
            throw new Exception("Failed to fetch the image. Status code: " . $response->getStatusCode());
        }

        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_starts_with($contentType, 'image/')) {
            throw new Exception("The URL does not point to an image file. Content-Type: " . $contentType);
        }

        $imageData = $response->getBody()->getContents();
        $base64Image = base64_encode($imageData);

        if ($base64Image === false) {
            throw new Exception("Failed to encode the image data to base64");
        }

        return $base64Image;
    } catch (GuzzleException $e) {
        Log::error("Error while loading the image: " . $e->getMessage());
        throw new Exception("Failed to fetch the image", 0, $e);
    }
}/**
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
