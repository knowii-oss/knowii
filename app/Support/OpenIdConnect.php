<?php

namespace App\Support;

/**
 * The configuration of sign-in with an OpenID Connect provider (#1385, ADR 0045): generic, per instance, all optional.
 * The feature is on only when the issuer, the client ID and the client secret are set and the issuer is a safe address
 * (https, or a loopback address in the local environment). Otherwise there is no button, the routes answer 404 and the
 * instance works as before.
 */
final class OpenIdConnect
{
    public const string REDIRECT_PATH = '/auth/oidc/redirect';

    public const string CALLBACK_PATH = '/auth/oidc/callback';

    private const int MAX_LABEL_LENGTH = 40;

    /**
     * The limits and the default of services.oidc.fresh_sign_in_seconds (#1521, ADR 0052)
     */
    public const int FRESH_SIGN_IN_DEFAULT_SECONDS = 300;

    public const int FRESH_SIGN_IN_MIN_SECONDS = 60;

    public const int FRESH_SIGN_IN_MAX_SECONDS = 900;

    public static function enabled(): bool
    {
        $issuer = self::issuer();

        return $issuer !== null
            && self::clientId() !== null
            && self::clientSecret() !== null
            && self::isAllowedAddress($issuer, true);
    }

    /**
     * The issuer exactly as configured (the value of `iss` must be equal to it, character for character)
     */
    public static function issuer(): ?string
    {
        return self::setting('issuer');
    }

    public static function clientId(): ?string
    {
        return self::setting('client_id');
    }

    public static function clientSecret(): ?string
    {
        return self::setting('client_secret');
    }

    /**
     * The scopes to ask for: always `openid`, never `offline_access` (no refresh token is asked or stored), and the entitlements
     * scope of the sign-in when that feature is on (#1386)
     *
     * @return list<string>
     */
    public static function scopes(): array
    {
        $configured = config('services.oidc.scopes');
        $scopes = ['openid'];
        // With the entitlements feature on (#1386, ADR 0047) the sign-in also asks for the scope that links this client to
        // the person at the provider: without it the provider refuses to answer for the person
        $wanted = preg_split('/[\s,]+/', (is_string($configured) ? $configured : '').' '.(OidcEntitlements::signInScope() ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($wanted as $scope) {
            if ($scope !== 'offline_access' && ! in_array($scope, $scopes, true)) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }

    /**
     * How old a new sign-in may be to prove the intent to delete an account without a password, and how long the proof
     * holds after the sign-in (#1521, ADR 0052): OIDC_FRESH_SIGN_IN_SECONDS, default 300, kept between 60 and 900
     */
    public static function freshSignInSeconds(): int
    {
        $value = config('services.oidc.fresh_sign_in_seconds');
        $seconds = is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : self::FRESH_SIGN_IN_DEFAULT_SECONDS);

        return max(self::FRESH_SIGN_IN_MIN_SECONDS, min(self::FRESH_SIGN_IN_MAX_SECONDS, $seconds));
    }

    /**
     * The text of the button: OIDC_LABEL, else the host name of the issuer
     */
    public static function label(): string
    {
        $label = self::setting('label');
        $clean = $label === null ? '' : self::cleanLabel($label);
        if ($clean !== '') {
            return $clean;
        }

        return self::hostOf(self::issuer() ?? '');
    }

    /**
     * The label that goes with an issuer: the one of this instance's provider, else the host name of that issuer
     */
    public static function labelFor(string $issuer): string
    {
        return self::enabled() && $issuer === self::issuer() ? self::label() : self::hostOf($issuer);
    }

    /**
     * The address that is registered at the provider: APP_URL followed by the callback path
     */
    public static function redirectUri(): string
    {
        $base = config('app.url');

        return rtrim(is_string($base) ? $base : '', '/').self::CALLBACK_PATH;
    }

    /**
     * The shared page prop (null when the feature is off, so that no page shows a button)
     *
     * @return array{label: string, error: string|null, status: string|null}|null
     */
    public static function pageProp(?string $error, ?string $status): ?array
    {
        return self::enabled() ? ['label' => self::label(), 'error' => $error, 'status' => $status] : null;
    }

    /**
     * Whether the server may call this address: https, or in the local environment a loopback address with http.
     * An address that holds a user name, a query or a fragment is never accepted for the issuer.
     */
    public static function isAllowedAddress(string $url, bool $isIssuer = false): bool
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        if ($host === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }
        if ($isIssuer && (isset($parts['query']) || strlen($url) > 512)) {
            return false;
        }

        if ($scheme === 'https') {
            return true;
        }

        return $scheme === 'http' && app()->isLocal() && self::isLoopback($host);
    }

    public static function isLoopback(string $host): bool
    {
        return in_array(strtolower(trim($host, '[]')), ['localhost', '127.0.0.1', '::1'], true);
    }

    private static function setting(string $key): ?string
    {
        $value = config('services.oidc.'.$key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function cleanLabel(string $label): string
    {
        $label = preg_replace('/[\p{C}]+/u', ' ', $label) ?? '';

        return mb_substr(trim($label), 0, self::MAX_LABEL_LENGTH);
    }

    private static function hostOf(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'your provider';
    }
}
