<?php

namespace App\Support;

/**
 * Builds the explicit origins that the Content Security Policy allows next to 'self'.
 *
 * The policy never accepts a whole scheme (`http:`, `https:`, `ws:`, `wss:`): every origin is listed.
 * The origins come from the environment, so acceptance, production and self-hosted instances set their own
 * (Reverb host, upload storage host, extra hosts). See config/secure-headers.php.
 */
final class ContentSecurityPolicySources
{
    /**
     * WebSocket origin of the Reverb server, as the browser connects to it.
     *
     * Example: scheme `https`, host `ws.knowii.net`, port 443 => `wss://ws.knowii.net`.
     */
    public static function reverbOrigin(?string $host, int|string|null $port, ?string $scheme): ?string
    {
        if (blank($host)) {
            return null;
        }

        $secure = ($scheme ?? 'https') === 'https';
        $port = (int) ($port ?? ($secure ? 443 : 80));
        $defaultPort = $secure ? 443 : 80;

        return sprintf('%s://%s%s', $secure ? 'wss' : 'ws', $host, $port === $defaultPort ? '' : ':'.$port);
    }

    /**
     * Origin of the Vite dev server (local environment only), for the styles, fonts and images it serves.
     *
     * @return list<string> The HTTP origin and the WebSocket origin used for hot module replacement
     */
    public static function viteDevServerOrigins(?string $environment, ?string $devServerUrl, int|string|null $vitePort): array
    {
        if ($environment !== 'local') {
            return [];
        }

        $url = filled($devServerUrl) ? (string) $devServerUrl : 'http://localhost:'.((int) ($vitePort ?? 5173));
        $origin = self::origin($url);

        if ($origin === null) {
            return [];
        }

        return [$origin, (string) preg_replace('/^http/', 'ws', $origin)];
    }

    /**
     * Origin (scheme, host and port) of a URL, or null when the URL has no host.
     */
    public static function origin(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $parts = parse_url((string) $url);

        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$parts['host'].$port;
    }

    /**
     * Sources from a comma-separated environment value. Bare schemes and wildcards are dropped.
     *
     * @return list<string>
     */
    public static function list(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        $sources = array_map('trim', explode(',', (string) $value));

        return array_values(array_filter($sources, static fn (string $source): bool => $source !== ''
            && $source !== '*'
            && ! self::isBareScheme($source)));
    }

    /**
     * Whether a source accepts a whole scheme (`https:`, `ws:`...), which the policy never allows.
     */
    public static function isBareScheme(string $source): bool
    {
        return (bool) preg_match('/^(https?|wss?):$/i', trim($source));
    }

    /**
     * Unique, non-empty sources.
     *
     * @param  array<int, string|null>  $sources
     * @return list<string>
     */
    public static function unique(array $sources): array
    {
        return array_values(array_unique(array_filter($sources, static fn (?string $source): bool => filled($source))));
    }
}
