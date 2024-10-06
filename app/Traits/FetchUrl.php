<?php

namespace App\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use http\Exception\RuntimeException;
use Nesk\Puphpeteer\Puppeteer;

trait FetchUrl
{
  /**
   * Fetch the content of a URL.
   *
   * @param string $url
   * @return string
   * @throws GuzzleException
   */
  final public function fetchUrlWithGuzzle(string $url): string
  {
    $client = $this->getClient();
    $response = $client->get($url);
    return (string) $response->getBody();
  }

  /**
   * Fetch the content of a URL using Puppeteer.
   *
   * @param string $url
   * @return string
   */
  final public function fetchUrl(string $url): string
  {
    // FIXME replace by curl calls?
    $puppeteer = new Puppeteer;
    $browser = $puppeteer->connect([
      'browserWSEndpoint' => env('BROWSERLESS_WS_ENDPOINT').'?token='.env('BROWSERLESS_TOKEN').'&launch={"headless":false,"stealth":true,"timeout":5000}',
    ]);

    try {
      $page = $browser->newPage();
      $page->goto($url, ['waitUntil' => 'networkidle0']);
      $html = $page->content();
    } finally {
      $browser->close();
    }

    return $html;
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

  /**
   * Get a configured Puppeteer browser instance.
   *
   * @return \Nesk\Puphpeteer\Browser
   */
  final public function getPuppeteerBrowser(): \Nesk\Puphpeteer\Browser
  {
    $puppeteer = new Puppeteer;
    return $puppeteer->launch([
      'headless' => true,
      'args' => ['--no-sandbox', '--disable-setuid-sandbox'],
    ]);
  }
}
