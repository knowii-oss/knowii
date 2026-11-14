<?php

namespace App\Traits;

use Carbon\Exceptions\InvalidFormatException;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;
use Mews\Purifier\Facades\Purifier;

/**
 * Process HTML, extract meta tags, title, description, and other metadata
 * Leverages the Open Graph protocol: https://ogp.me/
 */
trait ProcessHtml
{
    /**
     * Longest keyword kept from the page metadata.
     */
    private const int KEYWORD_MAX_LENGTH = 100;

    /**
     * Clean the HTML using HTMLPurifier
     *
     * @param  string  $html
     *
     * References:
     * https://github.com/mewebstudio/Purifier
     * https://htmlpurifier.org
     */
    final public function purifyHtml(string $html): string
    {
        $clean = Purifier::clean($html);

        return is_string($clean) ? $clean : '';
    }

    /**
     * The meta tags of the page, by lowercase name, property or http-equiv. The first tag wins
     * (the primary og:image comes first). Attribute order, quotes and HTML entities do not matter.
     *
     * @return array<string,string>
     */
    final public function getHtmlPageMeta(string $html): array
    {
        $metaArray = [];
        foreach ($this->getHtmlMetaTags($html) as [$key, $content]) {
            if (! array_key_exists($key, $metaArray)) {
                $metaArray[$key] = $content;
            }
        }

        return $metaArray;
    }

    /**
     * Every meta tag of the page, in document order, as [lowercase key, content].
     *
     * @return list<array{0: string, 1: string}>
     */
    final public function getHtmlMetaTags(string $html): array
    {
        $document = $this->parseHtml($html);
        if ($document === null) {
            return [];
        }

        $tags = [];
        foreach ($document->querySelectorAll('meta[content]') as $meta) {
            $key = $meta->getAttribute('name') ?? $meta->getAttribute('property') ?? $meta->getAttribute('http-equiv');
            if ($key === null || trim($key) === '') {
                continue;
            }
            $tags[] = [mb_strtolower(trim($key)), (string) $meta->getAttribute('content')];
        }

        return $tags;
    }

    /**
     * Title of the page: Open Graph, then Twitter, then the <title> element.
     */
    final public function getHtmlPageTitle(string $html): ?string
    {
        $metaArray = $this->getHtmlPageMeta($html);

        return $this->firstNonEmpty([
            $metaArray['og:title'] ?? null,
            $metaArray['twitter:title'] ?? null,
            $this->parseHtml($html)?->querySelector('title')?->textContent,
        ]);
    }

    /**
     * Description of the page: Open Graph, then Twitter, then <meta name="description">.
     */
    final public function getHtmlPageDescription(string $html): ?string
    {
        $metaArray = $this->getHtmlPageMeta($html);

        return $this->firstNonEmpty([
            $metaArray['og:description'] ?? null,
            $metaArray['twitter:description'] ?? null,
            $metaArray['description'] ?? null,
        ]);
    }

    /**
     * Publication date: article:published_time, then datePublished in the JSON-LD data. Null when invalid.
     */
    final public function getHtmlPublishedTime(string $html): ?string
    {
        return $this->validDate($this->getHtmlPageMeta($html)['article:published_time'] ?? $this->getHtmlJsonLdValue($html, 'datePublished'));
    }

    /**
     * Last modification date: article:modified_time, then dateModified in the JSON-LD data. Null when invalid.
     */
    final public function getHtmlModifiedTime(string $html): ?string
    {
        return $this->validDate($this->getHtmlPageMeta($html)['article:modified_time'] ?? $this->getHtmlJsonLdValue($html, 'dateModified'));
    }

    /**
     * Cover image: og:image (and its secure_url and url variants), then twitter:image.
     * A relative URL is resolved against $baseUrl (the URL of the page) when it is given.
     */
    final public function getHtmlCoverImage(string $html, ?string $baseUrl = null): ?string
    {
        $metaArray = $this->getHtmlPageMeta($html);

        $image = $this->firstNonEmpty([
            $metaArray['og:image'] ?? null,
            $metaArray['og:image:secure_url'] ?? null,
            $metaArray['og:image:url'] ?? null,
            $metaArray['twitter:image'] ?? null,
            $metaArray['twitter:image:src'] ?? null,
        ]);

        return $image === null || $baseUrl === null ? $image : $this->resolveUrl($image, $baseUrl);
    }

    /**
     * Icon of the site: the largest declared PNG, JPEG, WebP or GIF icon (apple-touch-icon and icon links), then
     * /favicon.png. ICO and SVG files are skipped: the image processing cannot read them (#1063).
     * The URL is resolved against $baseUrl (the URL of the page).
     */
    final public function getHtmlFaviconUrl(string $html, string $baseUrl): string
    {
        $document = $this->parseHtml($html);
        $candidates = [];

        foreach ($document?->querySelectorAll('link[rel][href]') ?? [] as $link) {
            $rel = ' '.strtolower(trim((string) $link->getAttribute('rel'))).' ';
            $href = trim((string) $link->getAttribute('href'));
            if ($href === '' || (! str_contains($rel, ' icon ') && ! str_contains($rel, ' apple-touch-icon '))) {
                continue;
            }

            $type = strtolower((string) $link->getAttribute('type'));
            $extension = strtolower(pathinfo((string) parse_url($href, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (in_array($extension, ['ico', 'svg'], true) || str_contains($type, 'icon') || str_contains($type, 'svg')) {
                continue;
            }

            // "180x180" or "32x32 64x64": keep the largest size; unknown sizes rank last
            preg_match_all('/(\d+)x\d+/i', (string) $link->getAttribute('sizes'), $sizes);
            $candidates[] = ['url' => $this->resolveUrl($href, $baseUrl), 'size' => $sizes[1] === [] ? 0 : max(array_map('intval', $sizes[1]))];
        }

        if ($candidates !== []) {
            usort($candidates, static fn (array $a, array $b) => $b['size'] <=> $a['size']);

            return $candidates[0]['url'];
        }

        return $this->resolveUrl('/favicon.png', $baseUrl);
    }

    final public function getHtmlCoverImageAlt(string $html): ?string
    {
        $metaArray = $this->getHtmlPageMeta($html);

        return $this->firstNonEmpty([$metaArray['og:image:alt'] ?? null, $metaArray['twitter:image:alt'] ?? null]);
    }

    final public function getHtmlOgType(string $html): ?string
    {
        return $this->firstNonEmpty([$this->getHtmlPageMeta($html)['og:type'] ?? null]);
    }

    /**
     * Twitter (X) account of the author (twitter:creator), else of the site (twitter:site).
     */
    final public function getHtmlTwitterUsername(string $html): ?string
    {
        $metaArray = $this->getHtmlPageMeta($html);

        return $this->firstNonEmpty([$metaArray['twitter:creator'] ?? null, $metaArray['twitter:site'] ?? null]);
    }

    /**
     * Language of the page: <meta http-equiv="content-language">, then og:locale, then <html lang>.
     */
    final public function getHtmlLanguage(string $html): ?string
    {
        $metaArray = $this->getHtmlPageMeta($html);

        return $this->firstNonEmpty([
            $metaArray['content-language'] ?? null,
            $metaArray['og:locale'] ?? null,
            $this->parseHtml($html)?->documentElement?->getAttribute('lang'),
        ]);
    }

    /**
     * A top-level value of the JSON-LD data of the page (also inside @graph and lists). Null when absent or not a string.
     */
    final public function getHtmlJsonLdValue(string $html, string $key): ?string
    {
        $document = $this->parseHtml($html);
        if ($document === null) {
            return null;
        }

        foreach ($document->querySelectorAll('script[type="application/ld+json"]') as $script) {
            $data = json_decode((string) $script->textContent, true);
            if (! is_array($data)) {
                continue;
            }
            $items = array_is_list($data) ? $data : [$data];
            if (isset($data['@graph']) && is_array($data['@graph'])) {
                $items = [...$items, ...$data['@graph']];
            }
            foreach ($items as $item) {
                if (is_array($item) && isset($item[$key]) && is_string($item[$key]) && trim($item[$key]) !== '') {
                    return trim($item[$key]);
                }
            }
        }

        return null;
    }

    /**
     * Parse the HTML with the HTML5 parser of PHP. Null for an empty page. Never throws on malformed HTML.
     */
    private function parseHtml(string $html): ?HTMLDocument
    {
        if (trim($html) === '') {
            return null;
        }

        return HTMLDocument::createFromString($html, LIBXML_NOERROR);
    }

    /**
     * The first value that is not empty after trimming and whitespace collapsing, or null.
     *
     * @param  list<string|null>  $values
     */
    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function validDate(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->isValid() ? $value : null;
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * Resolve a relative URL (/image.png, image.png, //cdn.example.com/image.png) against the URL of the page.
     */
    private function resolveUrl(string $url, string $baseUrl): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) === 1) {
            return $url;
        }

        $base = parse_url($baseUrl);
        if (! is_array($base) || ! isset($base['scheme'], $base['host'])) {
            return $url;
        }
        $origin = $base['scheme'].'://'.$base['host'].(isset($base['port']) ? ':'.$base['port'] : '');

        if (str_starts_with($url, '//')) {
            return $base['scheme'].':'.$url;
        }
        if (str_starts_with($url, '/')) {
            return $origin.$url;
        }

        $path = $base['path'] ?? '/';
        $directory = str_ends_with($path, '/') ? $path : (dirname($path) === '/' || dirname($path) === '\\' ? '/' : dirname($path).'/');

        return $origin.$directory.$url;
    }

    /**
     * Keywords of the page: the comma-separated keywords meta tag, then every article:tag meta tag.
     * Lowercase (multibyte safe), trimmed, entities decoded, without duplicates, in document order.
     * A keyword longer than KEYWORD_MAX_LENGTH characters is dropped.
     *
     * @return list<string>
     */
    final public function getHtmlKeywords(string $html): array
    {
        $candidates = [];
        foreach ($this->getHtmlMetaTags($html) as [$key, $content]) {
            if ($key === 'keywords') {
                array_push($candidates, ...explode(',', $content));
            }
        }
        foreach ($this->getHtmlMetaTags($html) as [$key, $content]) {
            if ($key === 'article:tag') {
                $candidates[] = $content;
            }
        }

        $keywords = [];
        foreach ($candidates as $candidate) {
            $keyword = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $candidate)));
            if ($keyword !== '' && mb_strlen($keyword) <= self::KEYWORD_MAX_LENGTH && ! in_array($keyword, $keywords, true)) {
                $keywords[] = $keyword;
            }
        }

        return $keywords;
    }
}
