<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait UrlCleanup
{
  /**
   * Clean up the URL by removing specified query string parameters.
   *
   * @param string $url
   * @return string
   */
  protected function cleanupUrl(string $url): string
  {
    $parsedUrl = parse_url($url);

    if (!isset($parsedUrl['query'])) {
      return $url;
    }

    parse_str($parsedUrl['query'], $queryParams);

    $paramsToRemove = [
      // UTM parameters used for tracking marketing campaigns
      'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',

      // Facebook and Google click identifiers
      'fbclid', 'gclid',

      // MailChimp parameters
      'mc_cid', 'mc_eid',

      // Common cache-busting and versioning parameters
      'timestamp', 'cache', 'nocache', 'v',

      // Referrer and source tracking
      'ref', 'referrer', 'source',

      // Affiliate and partner tracking
      'affid', 'affiliate', 'partner', 'prtnr',

      // Device and platform information
      'device', 'platform', 'os', 'browser', 'app_version',

      // Social media sharing parameters
      'share', 'tw_p', 'li_source',

      // Advertising related parameters
      'ad_id', 'adid', 'adgroup', 'campaign_id',

      // E-commerce related parameters
      'discount', 'coupon', 'promo',

      // Development and testing parameters
      'debug', 'test', 'perf',

      // Miscellaneous parameters often used in web applications
      'theme', 'callback', 'nonce', 'random'
    ];

    foreach ($paramsToRemove as $param) {
      unset($queryParams[$param]);
    }

    $cleanQuery = http_build_query($queryParams);

    $scheme   = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
    $host     = $parsedUrl['host'] ?? '';
    $port     = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
    $user     = $parsedUrl['user'] ?? '';
    $pass     = isset($parsedUrl['pass']) ? ':' . $parsedUrl['pass']  : '';
    $pass     = ($user || $pass) ? "$pass@" : '';
    $path     = $parsedUrl['path'] ?? '';
    $query    = $cleanQuery ? '?' . $cleanQuery : '';
    $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

    $cleanUrl = $scheme . $user . $pass . $host . $port . $path . $query . $fragment;

    // Remove trailing slashes
    $cleanUrl = rtrim($cleanUrl, '/');

    // Convert to lowercase
    $cleanUrl = Str::lower($cleanUrl);

    // Remove 'www.' if present
    $cleanUrl = preg_replace('/^(https?:\/\/)www\./i', '$1', $cleanUrl);

    return $cleanUrl;
  }
}
