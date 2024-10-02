<?php

namespace App\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

trait FetchUrl
{
  /**
   * Fetch the content of a URL.
   *
   * @param string $url
   * @return string
   * @throws GuzzleException
   */
  final public function fetchUrl(string $url): string
  {
    $client = $this->getClient();
    $response = $client->get($url);
    return (string) $response->getBody();
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
      'timeout' => 10,
      'verify' => true, // Verify SSL certificates
    ]);
  }
}
