<?php

namespace App\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

trait FetchUrl
{
  /**
   * Fetch the content of a URL.
   *
   * @param string $url
   * @return string
   * @throws GuzzleException
   */
  protected function fetchUrl(string $url): string
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
  protected function getFinalUrl(string $url): string
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
  protected function isUrlAvailable(string $url): bool
  {
    $client = $this->getClient();
    try {
      $response = $client->head($url);
      $statusCode = $response->getStatusCode();

      return $statusCode >= 200 && $statusCode < 300;
    } catch (ConnectException $e) {
      // FIXME log error
      return false;
    } catch (RequestException $e) {
      $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'Unknown';
      // FIXME log error and status code
      return false;
    } catch (\Exception $e) {
      // FIXME log error
      return false;
    }
  }

  /**
   * Get response headers for a URL.
   *
   * @param string $url
   * @return array
   * @throws GuzzleException
   */
  protected function getUrlHeaders(string $url): array
  {
    $client = $this->getClient();
    $response = $client->head($url);
    return $response->getHeaders();
  }

  /**
   * Get a configured Guzzle client.
   *
   * @return Client
   */
  private function getClient(): Client
  {
    return new Client([
      'headers' => [
        'User-Agent' => 'Knowii / 1.0 Crawler',
      ],
      'allow_redirects' => true,
      'timeout' => 10,
      'verify' => true, // Verify SSL certificates
    ]);
  }
}
