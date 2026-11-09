<?php

namespace App\Support;

use App\Enums\KnowiiResourceType;
use App\Traits\ProcessHtml;

/**
 * Detects the type of a captured page (ADR 0018 step 3, #1246). The first rule that matches wins: the JSON-LD @type,
 * og:type, the domain, the content type of the answer, then "article" when the page has readable content, else
 * "other".
 */
final class ResourceTypeDetector
{
    use ProcessHtml;

    /**
     * schema.org types (https://schema.org/) and their resource type
     */
    private const array JSON_LD_TYPES = [
        'Article' => KnowiiResourceType::Article,
        'NewsArticle' => KnowiiResourceType::Article,
        'BlogPosting' => KnowiiResourceType::Article,
        'TechArticle' => KnowiiResourceType::Article,
        'VideoObject' => KnowiiResourceType::Video,
        'PodcastEpisode' => KnowiiResourceType::Audio,
        'PodcastSeries' => KnowiiResourceType::Audio,
        'AudioObject' => KnowiiResourceType::Audio,
        'Book' => KnowiiResourceType::Book,
        'ScholarlyArticle' => KnowiiResourceType::Paper,
        'SoftwareApplication' => KnowiiResourceType::Tool,
        'WebApplication' => KnowiiResourceType::Tool,
        'SoftwareSourceCode' => KnowiiResourceType::Tool,
    ];

    /**
     * Hosts (and their subdomains) and their resource type
     */
    private const array DOMAINS = [
        'youtube.com' => KnowiiResourceType::Video,
        'youtu.be' => KnowiiResourceType::Video,
        'vimeo.com' => KnowiiResourceType::Video,
        'dailymotion.com' => KnowiiResourceType::Video,
        'twitch.tv' => KnowiiResourceType::Video,
        'ted.com' => KnowiiResourceType::Video,
        'podcasts.apple.com' => KnowiiResourceType::Audio,
        'arxiv.org' => KnowiiResourceType::Paper,
        'doi.org' => KnowiiResourceType::Paper,
        'pubmed.ncbi.nlm.nih.gov' => KnowiiResourceType::Paper,
        'github.com' => KnowiiResourceType::Tool,
        'gitlab.com' => KnowiiResourceType::Tool,
    ];

    public function detect(?string $html, string $url, string $contentType, bool $hasReadableContent): KnowiiResourceType
    {
        if ($html !== null && $html !== '') {
            $type = $this->fromJsonLd($html) ?? $this->fromOgType($this->getHtmlOgType($html));
            if ($type !== null) {
                return $type;
            }
        }

        return $this->fromDomain($url)
            ?? $this->fromContentType($contentType)
            ?? ($hasReadableContent ? KnowiiResourceType::Article : KnowiiResourceType::Other);
    }

    private function fromJsonLd(string $html): ?KnowiiResourceType
    {
        $document = $this->parseHtml($html);
        foreach ($document === null ? [] : $document->querySelectorAll('script[type="application/ld+json"]') as $script) {
            $data = json_decode((string) $script->textContent, true);
            if (! is_array($data)) {
                continue;
            }
            $items = array_is_list($data) ? $data : [$data];
            if (isset($data['@graph']) && is_array($data['@graph'])) {
                $items = [...$items, ...$data['@graph']];
            }
            foreach ($items as $item) {
                $types = is_array($item) ? ($item['@type'] ?? null) : null;
                foreach (is_array($types) ? $types : [$types] as $type) {
                    if (is_string($type) && isset(self::JSON_LD_TYPES[$type])) {
                        return self::JSON_LD_TYPES[$type];
                    }
                }
            }
        }

        return null;
    }

    private function fromOgType(?string $ogType): ?KnowiiResourceType
    {
        $ogType = strtolower(trim((string) $ogType));

        return match (true) {
            $ogType === 'article' => KnowiiResourceType::Article,
            str_starts_with($ogType, 'video') => KnowiiResourceType::Video,
            str_starts_with($ogType, 'music') => KnowiiResourceType::Audio,
            $ogType === 'book' || str_starts_with($ogType, 'books.') => KnowiiResourceType::Book,
            default => null,
        };
    }

    private function fromDomain(string $url): ?KnowiiResourceType
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (self::DOMAINS as $domain => $type) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $type;
            }
        }

        return null;
    }

    private function fromContentType(string $contentType): ?KnowiiResourceType
    {
        $contentType = strtolower($contentType);

        return match (true) {
            str_contains($contentType, 'application/pdf') => KnowiiResourceType::Paper,
            str_starts_with($contentType, 'audio/') => KnowiiResourceType::Audio,
            str_starts_with($contentType, 'video/') => KnowiiResourceType::Video,
            default => null,
        };
    }
}
