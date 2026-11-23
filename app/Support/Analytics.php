<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Plausible audience statistics (#1316, ADR 0064): cookieless, through a proxy. Off unless PLAUSIBLE_DOMAIN and
 * PLAUSIBLE_PROXY_URL are set, so an instance that sets neither (development, acceptance, self-hosted) sends nothing
 * to a statistics site such as stats.example.com. config/secure-headers.php allows the event address of the proxy under
 * the same condition.
 *
 * The application loads no script of another host for the statistics. Each page gets the shared prop `statistics`, and
 * the front end (apps/knowii/src/statistics.ts) sends one page view per visit of a page with the address of the prop: the
 * origin and the path of the page, and of the query only the campaign keys. The statistics never see the address of a page
 * that holds a link token or a signature (SecretAddresses): the prop is null there.
 */
final class Analytics
{
    /**
     * The routes of the pages that send no page view, next to the addresses that hold a secret: the page of an invite
     * link (#1391, ADR 0062)
     *
     * @var list<string>
     */
    public const array PAGES_WITHOUT_STATISTICS = ['invite-links.open', 'invite-links.show'];

    /**
     * The keys of the query that a page view keeps: the campaign keys that the statistics read. Every other key goes
     *
     * @var list<string>
     */
    public const array CAMPAIGN_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'ref', 'source'];

    /**
     * The shared prop `statistics`: where the page sends its page view, for which site, and the address that the page view
     * holds. Null when the instance sets no statistics, on a page of PAGES_WITHOUT_STATISTICS, and at an address that holds
     * a secret
     *
     * @return array{endpoint: string, domain: string, url: string}|null
     */
    public static function pageProp(Request $request): ?array
    {
        $domain = config('services.plausible.domain');
        $proxy = ContentSecurityPolicySources::origin(self::string(config('services.plausible.proxy_url')));

        if (! is_string($domain) || trim($domain) === '' || $proxy === null) {
            return null;
        }

        if ($request->routeIs(self::PAGES_WITHOUT_STATISTICS) || SecretAddresses::holdsASecret($request)) {
            return null;
        }

        return [
            'endpoint' => $proxy.'/api/v1/event',
            'domain' => trim($domain),
            'url' => self::pageViewUrl($request),
        ];
    }

    /**
     * The address of a page view: the origin and the path of the request, and of its query only the campaign keys with a
     * text value. Never the fragment: the server never gets it
     */
    public static function pageViewUrl(Request $request): string
    {
        $campaign = array_filter(
            array_filter(Arr::only($request->query->all(), self::CAMPAIGN_KEYS), is_string(...)),
            static fn (string $value): bool => $value !== '',
        );

        return $request->url().($campaign === [] ? '' : '?'.Arr::query($campaign));
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
