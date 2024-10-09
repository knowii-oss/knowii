<?php

namespace App\Traits;

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
    try {
      $client = $this->getClient();

      // References
      // https://docs.browserless.io/http-apis/content
      // Alternative: https://docs.browserless.io/http-apis/scrape
      $response = $client->post(env('BROWSERLESS_URL') . "/content?token=" . env("BROWSERLESS_TOKEN"), [
        'json' => [
          "url" => $url,
          "bestAttempt" => true,
          "gotoOptions" => [
            "timeout" => 10000,
            "waitUntil" => "networkidle2",
          ]
        ],
      ]);

      // With /scrape:
      // "element": [{ "selector": "html" }],

      $html = $response->getBody()->getContents();

      if($html === "" || $response->getStatusCode() !== 200) {
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
  final public function getFinalUrl(string $url): string
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
