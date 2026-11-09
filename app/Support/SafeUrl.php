<?php

namespace App\Support;

use App\Exceptions\BusinessException;
use Closure;

/**
 * Guards the requests that the server sends to addresses given by members or by fetched pages (#1231): only http
 * and https on the standard ports, and only hosts that resolve to public addresses. This blocks server-side request
 * forgery (loopback, private networks, cloud metadata, the Docker services).
 */
final class SafeUrl
{
    private const array ALLOWED_SCHEMES = ['http', 'https'];

    private const array ALLOWED_PORTS = [80, 443];

    /**
     * Resolves a host to its IP addresses. Replaced in tests: they must not depend on the DNS.
     *
     * @var (Closure(string): list<string>)|null
     */
    private static ?Closure $resolver = null;

    /**
     * @param  (Closure(string): list<string>)|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Check the URL and return the public IP address to connect to (pin it, so a second DNS answer cannot point
     * elsewhere).
     *
     * @throws BusinessException When the URL must not be fetched
     */
    public static function assertPublic(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true) || $host === '') {
            throw new BusinessException('Only http and https addresses can be fetched');
        }

        $port = $parts['port'] ?? null;
        if ($port !== null && ! in_array($port, self::ALLOWED_PORTS, true)) {
            throw new BusinessException('This address uses a port that cannot be fetched');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : self::resolve($host);
        if ($addresses === []) {
            throw new BusinessException('The address of this site cannot be found');
        }

        foreach ($addresses as $address) {
            // GLOBAL_RANGE refuses private, loopback, link-local, reserved, shared (100.64/10) and mapped addresses
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                throw new BusinessException('This address points to a private or local network');
            }
        }

        return $addresses[0];
    }

    /**
     * The curl option that pins the host of the URL to the checked address.
     *
     * @return array<int, list<string>>
     */
    public static function pinnedResolveOption(string $url, string $address): array
    {
        $parts = parse_url($url);
        $host = trim((string) ($parts['host'] ?? ''), '[]');
        $port = $parts['port'] ?? (strtolower((string) ($parts['scheme'] ?? '')) === 'https' ? 443 : 80);
        $target = str_contains($address, ':') ? '['.$address.']' : $address;

        return [CURLOPT_RESOLVE => [$host.':'.$port.':'.$target]];
    }

    /**
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        if (self::$resolver !== null) {
            return (self::$resolver)($host);
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (! is_array($records)) {
            return [];
        }

        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
