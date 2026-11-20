<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The application answers only for its own host names: the host of APP_URL, the names of TRUSTED_HOSTS and the loopback
 * names (the health checks of the server and of Docker call 127.0.0.1). A name matches as a whole, in any letter case and
 * with any port: a sub-domain is another name. A request for another host name gets the answer 400 before the application
 * does any work for it, and the log names the refused host name, at most once a minute. The middleware stands right after
 * the one that trusts the proxies (bootstrap/app.php), so it reads the host name that a trusted proxy gives.
 *
 * Every address that the application then builds (a link, a redirect, a signed address) has a host name of the
 * configuration: the host of APP_URL, or the name of TRUSTED_HOSTS that the request came by. A request by a loopback name
 * builds its addresses on the host of APP_URL. The port is the one of APP_URL, for each of these names (a second name is
 * served on the same port), and there is none when APP_URL has no port or the default port of its scheme. The scheme
 * stays the one of the request.
 *
 * The local environment accepts every host name and builds its addresses on the host of the request: a developer opens
 * the application by an address of the local network.
 */
class AnswerOnlyForOwnHostNames
{
    /**
     * The names of the machine itself, always accepted
     */
    public const array LOOPBACK_NAMES = ['localhost', '127.0.0.1', '[::1]'];

    /**
     * The log line for a refused host name
     */
    public const string REFUSED_MESSAGE = 'A request for a host name that is not in APP_URL or TRUSTED_HOSTS was refused';

    /**
     * The default port of each scheme: an address does not show it
     */
    private const array DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /**
     * @param  Closure(Request): Response  $next
     */
    final public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isLocal()) {
            // Each name is quoted: a "." or a "*" in it has no special meaning
            Request::setTrustedHosts(array_map(static fn (string $name): string => '^'.preg_quote($name).'$', self::names()));

            // This read checks the name. For a name that is not in the list it throws, and the framework answers 400
            try {
                $host = $request->getHost();
            } catch (SuspiciousOperationException $exception) {
                self::logRefusedName($exception);

                throw $exception;
            }

            $name = self::configuredName($host);

            if ($name !== null) {
                // The scheme of the request stays: a signed address is checked against the address of its request
                URL::useOrigin($request->getScheme().'://'.$name);
            }
        }

        return $next($request);
    }

    /**
     * The host names that the application answers for
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $ownName = self::ownName();

        return array_values(array_unique([...($ownName !== null ? [$ownName] : []), ...self::moreNames(), ...self::LOOPBACK_NAMES]));
    }

    /**
     * The host of APP_URL, as it is written there, or null when APP_URL is empty or has no host, as a name without a scheme
     * ("app.example.com")
     */
    public static function ownName(): ?string
    {
        $url = config('app.url');
        $name = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * The name of the configuration for the addresses of a request by the given host name (lower case, without a port):
     * the host of APP_URL, for that host and for a loopback name, or the name of TRUSTED_HOSTS itself, each with the port
     * of APP_URL. Null when APP_URL has no host and the name is not in TRUSTED_HOSTS
     */
    private static function configuredName(string $host): ?string
    {
        $ownName = self::ownName();
        $ownName = $ownName !== null ? strtolower($ownName) : null;

        if ($host !== $ownName) {
            foreach (self::moreNames() as $name) {
                if (strtolower($name) === $host) {
                    return $host.self::ownPort();
                }
            }
        }

        return $ownName !== null ? $ownName.self::ownPort() : null;
    }

    /**
     * The port of APP_URL as the end of a name (":8443"), or an empty text when APP_URL has no port or the default port
     * of its scheme (443 for https, 80 for http): a browser leaves the default port out of an address
     */
    private static function ownPort(): string
    {
        $url = config('app.url');
        $port = is_string($url) ? parse_url($url, PHP_URL_PORT) : null;
        if (! is_int($port)) {
            return '';
        }

        $scheme = parse_url((string) $url, PHP_URL_SCHEME);

        return is_string($scheme) && (self::DEFAULT_PORTS[strtolower($scheme)] ?? null) === $port ? '' : ':'.$port;
    }

    /**
     * Writes the refused host name to the log, once a minute for the whole instance and not once for each refused request.
     * Without a cache that answers, every request is the first: the line must reach the log. The line holds the name and
     * no other value of the request
     */
    private static function logRefusedName(SuspiciousOperationException $exception): void
    {
        try {
            $first = Cache::add('own-host-names:refused', true, 60);
        } catch (Throwable) {
            $first = true;
        }

        if ($first) {
            // The framework gives the name between quotes in the text of its exception, and in no other place
            preg_match('/"(.*)"/s', $exception->getMessage(), $match);

            Log::warning(self::REFUSED_MESSAGE, ['host' => mb_substr($match[1] ?? '', 0, 255)]);
        }
    }

    /**
     * The names of TRUSTED_HOSTS, as they are written there
     *
     * @return list<string>
     */
    private static function moreNames(): array
    {
        $names = [];

        $more = config('app.trusted_hosts');
        foreach (explode(',', is_string($more) ? $more : '') as $name) {
            $name = trim($name);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
