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

    private const string REFUSED_HOST = 'This address cannot be fetched.';

    /**
     * Ranges that GLOBAL_RANGE lets through but that are not a public host: NAT64 (an IPv4 address inside an IPv6
     * one), IPv4-compatible IPv6, site-local IPv6, and multicast
     *
     * @var list<array{0: string, 1: int}>
     */
    private const array SPECIAL_RANGES = [
        ['64:ff9b::', 96],
        ['64:ff9b:1::', 48],
        ['::', 96],
        ['fec0::', 10],
        ['ff00::', 8],
        ['224.0.0.0', 4],
    ];

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

        // One answer for every refused host: the answer never tells whether a name exists in the network of the server
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : self::resolve($host);
        if ($addresses === []) {
            throw new BusinessException(self::REFUSED_HOST);
        }

        foreach ($addresses as $address) {
            if (! self::isPublicAddress($address)) {
                throw new BusinessException(self::REFUSED_HOST);
            }
        }

        return $addresses[0];
    }

    /**
     * Whether an IP address is a public one that the server may connect to
     */
    public static function isPublicAddress(string $address): bool
    {
        // GLOBAL_RANGE refuses private, loopback, link-local, reserved, shared (100.64/10) and mapped addresses
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        $binary = (string) inet_pton($address);
        foreach (self::SPECIAL_RANGES as [$network, $bits]) {
            if (self::inRange($binary, (string) inet_pton($network), $bits)) {
                return false;
            }
        }

        return true;
    }

    private static function inRange(string $address, string $network, int $bits): bool
    {
        if (strlen($address) !== strlen($network)) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        if (strncmp($address, $network, $bytes) !== 0) {
            return false;
        }

        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($address[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
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
