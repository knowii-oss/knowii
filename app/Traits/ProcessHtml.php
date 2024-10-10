<?php

namespace App\Traits;


use Illuminate\Support\Carbon;
use Mews\Purifier\Facades\Purifier;

/**
 * Process HTML, extract meta tags, title, description, and other metadata
 * Leverages the Open Graph protocol: https://ogp.me/
 */
trait ProcessHtml
{
  /**
   * Clean the HTML using HTMLPurifier
   *
   * References:
   * https://github.com/mewebstudio/Purifier
   * https://htmlpurifier.org
   *
   * @param string $html
   * @return string
   */
  final public function purifyHtml(string $html): string
  {
    return Purifier::clean($html);
  }

  final public function getHtmlPageMeta(string $html): array
  {
    $metaArray = [];

    // Match all meta tags
    if (preg_match_all('/<meta\s+(?:[^>]*?\s+)?(?:name|property|http-equiv)=(["\'])(.*?)\1\s+content=(["\'])(.*?)\3[^>]*>/i', $html, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $name = strtolower($match[2]);
        $content = $match[4];
        $metaArray[$name] = $content;
      }
    }

    return $metaArray;
  }

  final public function getHtmlPageTitle(string $html): string|null
  {
    $retVal = null;

    preg_match('/<title>(.*?)<\/title>/i', $html, $matches);
    if (!empty($matches[1])) {
      $retVal = trim($matches[1]);
    }

    if (!empty($retVal)) {
      return $retVal;
    }

    // Fallback to page metadata
    $metaArray = $this->getHtmlPageMeta($html);

    if (!empty($metaArray['og:title'])) {
      $retVal = trim($metaArray['og:title']);
    }

    if (empty($retVal) && !empty($metaArray['twitter:title'])) {
      $retVal = trim($metaArray['twitter:title']);
    }

    if (empty($retVal)) {
      return null;
    }

    return $retVal;
  }

  final public function getHtmlPageDescription(string $html): string|null
  {
    $metaArray = $this->getHtmlPageMeta($html);

    $retVal = null;

    if (!empty($metaArray['description'])) {
      $retVal = trim($metaArray['description']);
    }

    if (empty($retVal) && !empty($metaArray['og:description'])) {
      $retVal = trim($metaArray['og:description']);
    }

    if (empty($retVal) && !empty($metaArray['twitter:description'])) {
      $retVal = trim($metaArray['twitter:description']);
    }

    if (empty($retVal)) {
      return null;
    }

    return $retVal;
  }

  final public function getHtmlPublishedTime(string $html): string|null
  {
    $metaArray = $this->getHtmlPageMeta($html);

    $retVal = null;

    if (!empty($metaArray['article:published_time'])) {
      $retVal = trim($metaArray['article:published_time']);
    }

    if (empty($retVal)) {
      return null;
    }

    if(!Carbon::parse($retVal)->isValid()) {
      return null;
    }

    return $retVal;
  }

  final public function getHtmlModifiedTime(string $html): string|null
  {
    $metaArray = $this->getHtmlPageMeta($html);

    $retVal = null;

    if (!empty($metaArray['article:modified_time'])) {
      $retVal = trim($metaArray['article:modified_time']);
    }

    if (empty($retVal)) {
      return null;
    }

    if(!Carbon::parse($retVal)->isValid()) {
      return null;
    }

    return $retVal;
  }

  final public function getHtmlCoverImage(string $html): string|null
  {
    $metaArray = $this->getHtmlPageMeta($html);

    $retVal = null;

    if (!empty($metaArray['og:image'])) {
      $retVal = trim($metaArray['og:image']);
    }

    if (empty($retVal) && !empty($metaArray['og:image:secure_url'])) {
      $retVal = trim($metaArray['og:image:secure_url']);
    }

    if (empty($retVal) && !empty($metaArray['og:image:url'])) {
      $retVal = trim($metaArray['og:image:url']);
    }

    if (empty($retVal) && !empty($metaArray['twitter:image'])) {
      $retVal = trim($metaArray['twitter:image']);
    }

    if (empty($retVal)) {
      return null;
    }

    return $retVal;
  }

  final public function getHtmlCoverImageAlt(string $html): string|null
  {
    $metaArray = $this->getHtmlPageMeta($html);

    $retVal = null;

    if (!empty($metaArray['og:image:alt'])) {
      $retVal = trim($metaArray['og:image:alt']);
    }

    if (empty($retVal)) {
      return null;
    }

    return $retVal;
  }

  final public function getHtmlOgType(string $html): string|null
  {
    $metaArray = $this->getHtmlPageMeta($html);

    $retVal = null;

    if (!empty($metaArray['og:type'])) {
      $retVal = trim($metaArray['og:type']);
    }

    if (empty($retVal)) {
      return null;
    }

    return $retVal;
  }

  final public function getHtmlTwitterUsername(string $html): string|null
  {
    $metaArray = $this->getHtmlPageMeta($html);

    $retVal = null;

    if (!empty($metaArray['twitter:creator'])) {
      $retVal = trim($metaArray['twitter:creator']);
    }

    if (empty($retVal) && !empty($metaArray['twitter:site'])) {
      $retVal = trim($metaArray['twitter:site']);
    }

    if (empty($retVal)) {
      return null;
    }

    return $retVal;
  }

  final public function getHtmlLanguage(string $html): string|null
  {
    $retVal = null;

    // Try to get language from meta http-equiv="content-language"
    if (preg_match('/<meta\s+http-equiv=["\']content-language["\'][^>]*content=["\'](.+?)["\'][^>]*>/i', $html, $matches)) {
      $retVal = trim($matches[1]);
    }

    // Fallback to other metadata
    $metaArray = $this->getHtmlPageMeta($html);
    if (empty($retVal) && !empty($metaArray['og:locale'])) {
      $retVal = trim($metaArray['og:locale']);
    }

    // If still not found, try html lang attribute
    if (empty($retVal) && preg_match('/<html[^>]+lang=["\'](.+?)["\'][^>]*>/i', $html, $matches)) {
      $retVal = trim($matches[1]);
    }

    if (empty($retVal)) {
      return null;
    }

    return $retVal;
  }

  final public function getHtmlKeywords(string $html): array
  {
    $retVal = [];
    $metaArray = $this->getHtmlPageMeta($html);

    // Process keywords meta tag
    if (!empty($metaArray['keywords'])) {
        $keywords = explode(',', $metaArray['keywords']);
        foreach ($keywords as $keyword) {
            $keyword = trim(strtolower($keyword));
            if (!empty($keyword) && !in_array($keyword, $retVal) && strlen($keyword) <= 100) {
                $retVal[] = $keyword;
            }
        }
    }

    // Process article:tag meta tags (if present)
    if (preg_match_all('/<meta\s+(?:[^>]*?\s+)?property=(["\'])article:tag\1\s+content=(["\'])(.*?)\2[^>]*>/i', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $tag = trim(strtolower($match[3]));
            if (!empty($tag) && !in_array($tag, $retVal)) {
                $retVal[] = $tag;
            }
        }
    }

    return $retVal;
  }
}
