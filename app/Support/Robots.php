<?php

namespace App\Support;

/**
 * Crawling rules of the instance (#104, config/robots.php).
 */
final class Robots
{
    public static function indexingAllowed(): bool
    {
        return (bool) config('robots.allow_indexing', false);
    }

    /**
     * The robots.txt of the instance: AI crawlers refused everywhere, then the rules for every other crawler.
     */
    public static function txt(): string
    {
        $lines = ['# Knowii. Member content is never crawled, and AI crawlers are refused.', ''];

        /** @var list<string> $aiCrawlers */
        $aiCrawlers = (array) config('robots.ai_crawlers', []);
        foreach ($aiCrawlers as $crawler) {
            $lines[] = 'User-agent: '.$crawler;
        }
        if ($aiCrawlers !== []) {
            $lines[] = 'Disallow: /';
            $lines[] = '';
        }

        $lines[] = 'User-agent: *';
        if (! self::indexingAllowed()) {
            $lines[] = 'Disallow: /';
        } else {
            /** @var list<string> $paths */
            $paths = (array) config('robots.disallow', []);
            foreach ($paths as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = 'Allow: /';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * The robots directive of a response: index only public pages for guests, when the instance allows indexing.
     */
    public static function directive(bool $authenticated, bool $isApi): string
    {
        return self::indexingAllowed() && ! $authenticated && ! $isApi ? 'index, follow, max-image-preview:large' : 'noindex, nofollow';
    }
}
