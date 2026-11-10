<?php

namespace App\Support;

/**
 * Plausible audience statistics (#1316): cookieless, through a proxy. Off unless PLAUSIBLE_DOMAIN and
 * PLAUSIBLE_PROXY_URL are set, so development, acceptance and self-hosted instances send nothing to the knowii.net
 * site. config/secure-headers.php allows the proxy origin under the same condition.
 */
final class Analytics
{
    /**
     * @return array{domain: string, script_url: string, api_url: string}|null
     */
    public static function plausible(): ?array
    {
        $domain = config('services.plausible.domain');
        $proxy = ContentSecurityPolicySources::origin(self::string(config('services.plausible.proxy_url')));

        if (! is_string($domain) || trim($domain) === '' || $proxy === null) {
            return null;
        }

        return [
            'domain' => trim($domain),
            'script_url' => $proxy.'/content/script.js',
            'api_url' => $proxy.'/api/v1/event',
        ];
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
