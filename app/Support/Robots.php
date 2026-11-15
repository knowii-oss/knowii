<?php

namespace App\Support;

use App\Enums\KnowiiPageKind;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\Community;
use App\Models\Space;
use Illuminate\Http\Request;

/**
 * Crawling rules of the instance (#104, config/robots.php) and the indexing choice of each community (#1487, ADR 0044).
 */
final class Robots
{
    private const string INDEX = 'index, follow, max-image-preview:large';

    private const string NOINDEX = 'noindex, nofollow';

    /**
     * The pages of a community that a visitor can read, with the types of space that each one may belong to. Every other
     * address of /community/ (about, members, messages, settings, ...) is never indexed.
     */
    private const array READER_ROUTES = [
        'communities.show' => [],
        'spaces.show' => [KnowiiSpaceType::Discussion, KnowiiSpaceType::Pages, KnowiiSpaceType::Course, KnowiiSpaceType::Resources],
        'posts.show' => [KnowiiSpaceType::Discussion],
        'pages.show' => [KnowiiSpaceType::Pages, KnowiiSpaceType::Course],
        'resources.show' => [KnowiiSpaceType::Resources],
    ];

    public static function indexingAllowed(): bool
    {
        return (bool) config('robots.allow_indexing', false);
    }

    /**
     * The robots.txt of the instance: AI crawlers refused everywhere, then the rules for every other crawler. The pages of
     * the communities are not blocked as a whole: each response carries its own decision (#1487), and the addresses that
     * can never be public stay blocked.
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
     * The robots directive of a response: index only public pages for guests, when the instance allows indexing. A page of
     * a community is a public page only when `$communityPageIndexable` is true (null: not a page of a community).
     */
    public static function directive(bool $authenticated, bool $isApi, ?bool $communityPageIndexable = null): string
    {
        return self::indexingAllowed() && ! $authenticated && ! $isApi && $communityPageIndexable !== false ? self::INDEX : self::NOINDEX;
    }

    /**
     * The directive of a request: the one place that the header (AddRobotsHeader) and the meta tag (app.blade.php) both
     * ask, so that they agree (#1487). A community page is indexed only when all of these hold: the reader is a visitor,
     * the page is one that a visitor can read in a public space (or the public community page), the community turned
     * indexing on, the instance allows indexing. Everything else, also a deleted post, is noindex.
     */
    public static function forRequest(Request $request): string
    {
        $isApi = $request->is('api/*');
        $authenticated = $request->user() !== null;

        // The cheap checks first: no query for a signed-in reader, the API or an instance that does not index
        $communityPage = $request->is('community/*') ? ! $isApi && ! $authenticated && self::indexingAllowed() && self::isIndexablePage($request) : null;

        return self::directive($authenticated, $isApi, $communityPage);
    }

    /**
     * Whether the page of a community that the request reads is one that a visitor reads in a public space, in a
     * community that allows indexing. The answer is kept for the request: the header and the meta tag ask once.
     */
    private static function isIndexablePage(Request $request): bool
    {
        /** @var bool|null $known */
        $known = $request->attributes->get('robots.indexable');
        if ($known !== null) {
            return $known;
        }

        $indexable = self::resolveIndexablePage($request);
        $request->attributes->set('robots.indexable', $indexable);

        return $indexable;
    }

    private static function resolveIndexablePage(Request $request): bool
    {
        $route = $request->route();
        $name = $route?->getName();
        if ($route === null || $name === null || ! array_key_exists($name, self::READER_ROUTES)) {
            return false;
        }

        $community = Community::query()->where('slug', $route->parameter('communitySlug'))->first();
        if ($community === null || ! $community->allowsIndexing()) {
            return false;
        }

        if ($name === 'communities.show') {
            return PublicCommunity::exists($community);
        }

        $types = self::READER_ROUTES[$name];
        $space = $community->spaces()
            ->where('slug', $route->parameter('spaceSlug'))
            ->where('visibility', KnowiiSpaceVisibility::Public->value)
            ->whereIn('type', array_map(static fn (KnowiiSpaceType $type): string => $type->value, $types))
            ->first();

        $slug = $route->parameter('postSlug') ?? $route->parameter('pageSlug') ?? $route->parameter('resourceSlug');

        return $space !== null && self::contentExists($name, $space, is_string($slug) ? $slug : null);
    }

    /**
     * The item of the page exists and holds content: a marker of a deleted post (#1344) and a section heading do not
     */
    private static function contentExists(string $routeName, Space $space, ?string $slug): bool
    {
        return match ($routeName) {
            'spaces.show' => true,
            'posts.show' => $slug !== null && $space->posts()->where('slug', $slug)->whereNull('deleted_at')->exists(),
            'pages.show' => $slug !== null && $space->pages()->where('slug', $slug)->where('kind', '!=', KnowiiPageKind::Section->value)->exists(),
            'resources.show' => $slug !== null && $space->resources()->where('slug', $slug)->exists(),
            default => false,
        };
    }
}
