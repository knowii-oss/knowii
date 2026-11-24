<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a sign-in, the application goes only to an address of its own host names (ADR 0068). The session keeps the
 * address to open after the sign-in (the intended address of the framework). The framework takes it from the address of
 * the request, or from the `Referer` header for a request that is not a GET. This middleware keeps the value only when
 * it is an address of the application: a path, or an http or https address whose host is one of the own host names
 * (AnswerOnlyForOwnHostNames) or the host of the request. It removes any other value.
 *
 * It checks the session twice: before the request goes on, so that no reader of the value gets another address (the
 * answers of Fortify, the sign-in link, the sign-up link, the callback of the provider, the address check), and after the
 * answer, so that the session stores no other address. It stands right after the start of the session in the `web`
 * group (bootstrap/app.php): every middleware and every route that writes the value runs inside it.
 */
class LandOnlyOnOwnHostNames
{
    /**
     * Where the session keeps the intended address (the key of the framework)
     */
    private const string SESSION_KEY = 'url.intended';

    /**
     * @param  Closure(Request): Response  $next
     */
    final public function handle(Request $request, Closure $next): Response
    {
        self::keepOnlyAnOwnAddress($request);

        $response = $next($request);

        self::keepOnlyAnOwnAddress($request);

        return $response;
    }

    /**
     * True when the text is an address of the application: a path that starts with one "/", or an http or https address
     * whose host is one of the given names, in any letter case and with any port. A text with a backslash, a space, a
     * control character, or a user name before the host is never one: the application writes no such address.
     *
     * @param  list<string>  $names  the accepted host names, in any letter case
     */
    public static function isOwnAddress(string $address, array $names): bool
    {
        if ($address === '' || preg_match('/[\\\\\x00-\x20\x7f]/', $address) === 1) {
            return false;
        }

        $parts = parse_url($address);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = $parts['host'] ?? null;
        if ($host === null) {
            // A path of the application: the framework builds its address on the own host name
            return ! isset($parts['scheme']) && str_starts_with($address, '/') && ! str_starts_with($address, '//');
        }

        if (! in_array(strtolower($parts['scheme'] ?? 'https'), ['http', 'https'], true)) {
            return false;
        }

        return in_array(strtolower($host), array_map(strtolower(...), $names), true);
    }

    /**
     * Removes the intended address of the session when it is not an address of the application
     */
    private static function keepOnlyAnOwnAddress(Request $request): void
    {
        if (! $request->hasSession() || ! $request->session()->has(self::SESSION_KEY)) {
            return;
        }

        $intended = $request->session()->get(self::SESSION_KEY);

        // The host of the request is an own host name: AnswerOnlyForOwnHostNames refused any other one. In the local
        // environment, which accepts every host name, it is the name that the developer opened
        $names = [...AnswerOnlyForOwnHostNames::names(), $request->getHost()];

        if (! is_string($intended) || ! self::isOwnAddress($intended, $names)) {
            $request->session()->forget(self::SESSION_KEY);
        }
    }
}
