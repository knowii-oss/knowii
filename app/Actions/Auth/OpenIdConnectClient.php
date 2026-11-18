<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\ReadsOpenIdConnectIdentities;
use App\Exceptions\BusinessException;
use App\Exceptions\OpenIdConnectException;
use App\Support\OpenIdConnect;
use App\Support\OpenIdIdentity;
use App\Support\SafeUrl;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The generic OpenID Connect client of #1385 (ADR 0045): authorization code flow with PKCE (S256), `state` and `nonce`.
 *
 * - The discovery document and the keys are read from the issuer and cached for one hour. The keys are read again once
 *   when the key id of a token is unknown. A failure is never cached.
 * - Every request has a timeout, goes to https (or, in the local environment, to a loopback address), never follows a
 *   redirect, and goes to a public address (SafeUrl, the host is pinned): the discovery document cannot point the
 *   server at an internal service.
 * - The ID token is checked in this order: the algorithm (only EdDSA, RS256 and ES256, never `none`, never an HMAC),
 *   the key (found by `kid`, its type must fit the algorithm: a public key is never an HMAC secret), the signature, then
 *   `iss`, `aud`, `azp`, `exp`, `iat`, the nonce and `sub`.
 * - Only the identity leaves this class. The access token is never read and no refresh token is asked (the scope
 *   `offline_access` is never sent).
 *
 * @phpstan-type Json array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|null>|null>|null>|null>
 * @phpstan-type Claims array<string, scalar|array<array-key, scalar|null>|null>
 */
final class OpenIdConnectClient implements ReadsOpenIdConnectIdentities
{
    public const int CACHE_SECONDS = 3600;

    private const int HTTP_TIMEOUT_SECONDS = 10;

    private const int HTTP_CONNECT_TIMEOUT_SECONDS = 5;

    private const int MAX_BODY_BYTES = 262144;

    /**
     * The keys are read again for an unknown key id of a change call at most this often
     */
    private const int KEY_REFRESH_SECONDS = 60;

    private const int MAX_TOKEN_BYTES = 16384;

    private const int CLOCK_SKEW_SECONDS = 60;

    private const int MIN_RSA_BITS = 2048;

    /**
     * The algorithms that an ID token may use, by type of key. The same list decides which keys are used.
     */
    private const array ALGORITHMS = ['EdDSA', 'RS256', 'ES256'];

    /**
     * The members of a key (a JWK) that the client keeps: public parts only
     */
    private const array JWK_FIELDS = ['kty', 'kid', 'use', 'alg', 'crv', 'x', 'y', 'n', 'e'];

    /**
     * @throws OpenIdConnectException
     */
    final public function authorizationUrl(string $state, string $nonce, string $codeChallenge, bool $freshSignIn = false): string
    {
        $endpoint = $this->discovery()['authorization_endpoint'];
        $parameters = [
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => OpenIdConnect::redirectUri(),
            'scope' => implode(' ', OpenIdConnect::scopes()),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];
        if ($freshSignIn) {
            // The proof of a deletion (#1521, ADR 0052): the provider must ask the person to sign in again, even when they
            // are signed in there. The browser can remove these, and a provider can ignore them: auth_time is the proof
            $parameters['max_age'] = '0';
            $parameters['prompt'] = 'login';
        }
        $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').$query;
    }

    /**
     * @throws OpenIdConnectException
     */
    final public function identity(string $code, string $codeVerifier, string $nonce): OpenIdIdentity
    {
        $discovery = $this->discovery();
        $idToken = $this->exchange($discovery, $code, $codeVerifier);
        $claims = $this->validate($idToken, $nonce, $discovery['jwks_uri']);

        $subject = $claims['sub'] ?? null;
        if (! is_string($subject) || $subject === '' || strlen($subject) > 255 || preg_match('/[\x00-\x1f\x7f]/', $subject) === 1) {
            throw new OpenIdConnectException('bad_subject');
        }
        $email = $claims['email'] ?? null;
        $name = $claims['name'] ?? null;
        $authTime = $claims['auth_time'] ?? null;

        return new OpenIdIdentity(
            issuer: $this->issuer(),
            subject: $subject,
            email: is_string($email) && $email !== '' ? $email : null,
            // Exactly the boolean true: the string "true", 1 and a missing claim are not a verified address
            emailVerified: ($claims['email_verified'] ?? null) === true,
            name: is_string($name) && trim($name) !== '' ? $name : null,
            // Exactly an integer: a text or a decimal number is not a time of sign-in (#1521)
            authTime: is_int($authTime) ? $authTime : null,
        );
    }

    /**
     * The discovery document, checked and cached. Only the members that the client uses are kept.
     *
     * @return array{authorization_endpoint: string, token_endpoint: string, jwks_uri: string, token_auth: 'basic'|'post'}
     *
     * @throws OpenIdConnectException
     */
    private function discovery(): array
    {
        $issuer = $this->issuer();
        $key = 'oidc:discovery:'.hash('sha256', $issuer);

        $cached = Cache::get($key);
        if (is_array($cached)) {
            /** @var Json $cached What this class stored: the endpoints, checked again */
            $endpoints = $this->endpointsOf($cached, false);
            if ($endpoints !== null) {
                return $endpoints;
            }
        }

        $document = $this->fetchJson(rtrim($issuer, '/').'/.well-known/openid-configuration');
        $endpoints = $this->endpointsOf($document, true);
        if ($endpoints === null) {
            throw new OpenIdConnectException('discovery_refused');
        }

        Cache::put($key, $endpoints, self::CACHE_SECONDS);

        return $endpoints;
    }

    /**
     * The endpoints come from the provider that the operator of the instance configured (OIDC_ISSUER), which the platform
     * trusts for every sign-in: the document must name that issuer, it comes over https with a checked certificate and
     * without a redirect, and every endpoint must be an https address (ADR 0045). The security scan (ADR 0051) therefore
     * treats the checked endpoints as safe for a request and for a redirect.
     *
     * @param  Json  $document
     * @return array{authorization_endpoint: string, token_endpoint: string, jwks_uri: string, token_auth: 'basic'|'post'}|null
     *
     * @psalm-taint-escape ssrf
     * @psalm-taint-escape header
     */
    private function endpointsOf(array $document, bool $isDocument): ?array
    {
        $authorization = $document['authorization_endpoint'] ?? null;
        $token = $document['token_endpoint'] ?? null;
        $jwks = $document['jwks_uri'] ?? null;

        if ($isDocument) {
            // The document must be the one of this issuer (a document that names another issuer is refused)
            if (($document['issuer'] ?? null) !== $this->issuer()) {
                return null;
            }
            $methods = $document['code_challenge_methods_supported'] ?? null;
            if (is_array($methods) && ! in_array('S256', $methods, true)) {
                return null;
            }
            $responseTypes = $document['response_types_supported'] ?? null;
            if (is_array($responseTypes) && ! in_array('code', $responseTypes, true)) {
                return null;
            }
            $algorithms = $document['id_token_signing_alg_values_supported'] ?? null;
            if (is_array($algorithms) && array_filter(self::ALGORITHMS, static fn (string $algorithm): bool => in_array($algorithm, $algorithms, true)) === []) {
                return null;
            }
            $authMethods = $document['token_endpoint_auth_methods_supported'] ?? null;
            if (is_array($authMethods)) {
                $auth = in_array('client_secret_basic', $authMethods, true) ? 'basic' : (in_array('client_secret_post', $authMethods, true) ? 'post' : null);
            } else {
                // The default of the specification
                $auth = 'basic';
            }
        } else {
            $auth = ($document['token_auth'] ?? null) === 'post' ? 'post' : 'basic';
        }

        if ($auth === null || ! is_string($authorization) || ! is_string($token) || ! is_string($jwks)) {
            return null;
        }
        foreach ([$authorization, $token, $jwks] as $url) {
            if (! OpenIdConnect::isAllowedAddress($url)) {
                return null;
            }
        }

        return ['authorization_endpoint' => $authorization, 'token_endpoint' => $token, 'jwks_uri' => $jwks, 'token_auth' => $auth];
    }

    /**
     * Send the code to the token endpoint and return the ID token. The access token and any refresh token are ignored.
     *
     * @param  array{authorization_endpoint: string, token_endpoint: string, jwks_uri: string, token_auth: 'basic'|'post'}  $discovery
     *
     * @throws OpenIdConnectException
     */
    private function exchange(array $discovery, string $code, string $codeVerifier): string
    {
        $response = $this->postToTokenEndpoint($discovery, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => OpenIdConnect::redirectUri(),
            'code_verifier' => $codeVerifier,
        ]);

        $body = $this->decode($response, 'token_endpoint_error');
        $idToken = $body['id_token'] ?? null;
        if (! is_string($idToken) || $idToken === '' || strlen($idToken) > self::MAX_TOKEN_BYTES) {
            throw new OpenIdConnectException('no_id_token');
        }

        return $idToken;
    }

    /**
     * Post a form to the token endpoint with the authentication that the provider supports: the same for the code
     * exchange and for the client credentials grant
     *
     * @param  array{authorization_endpoint: string, token_endpoint: string, jwks_uri: string, token_auth: 'basic'|'post'}  $discovery
     * @param  array<string, string>  $form
     *
     * @throws OpenIdConnectException
     */
    private function postToTokenEndpoint(array $discovery, array $form): Response
    {
        try {
            $request = $this->request($discovery['token_endpoint'])->asForm();
            if ($discovery['token_auth'] === 'basic') {
                // RFC 6749, 2.3.1: the identifier and the secret are form-encoded before the Basic encoding
                $request = $request->withBasicAuth(urlencode($this->clientId()), urlencode($this->clientSecret()));
            } else {
                $form['client_id'] = $this->clientId();
                $form['client_secret'] = $this->clientSecret();
            }

            return $request->post($discovery['token_endpoint'], $form);
        } catch (ConnectionException) {
            throw new OpenIdConnectException('token_endpoint_unreachable');
        }
    }

    /**
     * A token of the client credentials grant (#1386, ADR 0047): the client itself, never a member. The caller caches it.
     * The status of a refusal is kept on the exception, so that a caller can tell a configuration problem (400, 401, 403)
     * from a provider that is down (5xx) or a limit (429).
     *
     * @return array{token: string, expires_in: int}
     *
     * @throws OpenIdConnectException
     */
    final public function clientCredentialsToken(string $scope, ?string $resource): array
    {
        $form = ['grant_type' => 'client_credentials', 'scope' => $scope];
        if ($resource !== null) {
            $form['resource'] = $resource;
        }

        $response = $this->postToTokenEndpoint($this->discovery(), $form);
        if ($response->status() !== 200) {
            throw new OpenIdConnectException('token_endpoint_error', OpenIdConnectException::GENERIC_MESSAGE, $response->status());
        }
        $body = $this->decode($response, 'token_endpoint_error');

        $token = $body['access_token'] ?? null;
        $type = $body['token_type'] ?? null;
        if (! is_string($token) || $token === '' || strlen($token) > self::MAX_TOKEN_BYTES || ! is_string($type) || strtolower($type) !== 'bearer') {
            throw new OpenIdConnectException('no_access_token');
        }
        $lifetime = $body['expires_in'] ?? null;

        // A token without a lifetime is used once and not kept (1 second)
        return ['token' => $token, 'expires_in' => is_int($lifetime) ? max(1, min($lifetime, 3600)) : 1];
    }

    /**
     * GET from the provider with a bearer token and hand the answer back: the caller decides what each status means
     *
     * @throws OpenIdConnectException
     */
    final public function authorizedGet(string $url, string $accessToken): Response
    {
        try {
            return $this->request($url)->withToken($accessToken)->get($url);
        } catch (ConnectionException) {
            throw new OpenIdConnectException('provider_unreachable');
        }
    }

    /**
     * Check a token that the provider signed for this client (the change call of #1386, ADR 0047) and return its claims.
     * In this order: the shape, the algorithm (never `none`, never an HMAC), the header `typ`, the key (by `kid`), the
     * signature, then `iss`, `aud`, `exp` (strictly in the future, no skew), and `iat` (present, at most `$maxAgeSeconds`
     * old and not in the future beyond the skew). The caller checks the claims that are its own (`event`, `sub`, `jti`).
     *
     * @return Claims
     *
     * @throws OpenIdConnectException
     */
    final public function verifySignedEvent(string $jws, string $type, int $maxAgeSeconds): array
    {
        if (strlen($jws) > self::MAX_TOKEN_BYTES) {
            throw new OpenIdConnectException('bad_token');
        }
        // The shape, the algorithm and the type come before any request to the provider
        [$algorithm, $keyId] = $this->header($jws, $type);
        $claims = $this->verified($jws, $algorithm, $keyId, $this->discovery()['jwks_uri'], true);

        if (($claims['iss'] ?? null) !== $this->issuer()) {
            throw new OpenIdConnectException('wrong_issuer');
        }
        $audience = $claims['aud'] ?? null;
        $audiences = is_string($audience) ? [$audience] : (is_array($audience) ? array_values($audience) : []);
        if (! in_array($this->clientId(), $audiences, true)) {
            throw new OpenIdConnectException('wrong_audience');
        }

        $now = now()->getTimestamp();
        $expires = $claims['exp'] ?? null;
        if ((! is_int($expires) && ! is_float($expires)) || $expires <= $now) {
            throw new OpenIdConnectException('expired');
        }
        $issuedAt = $claims['iat'] ?? null;
        if ((! is_int($issuedAt) && ! is_float($issuedAt)) || $issuedAt > $now + self::CLOCK_SKEW_SECONDS || $issuedAt < $now - $maxAgeSeconds) {
            throw new OpenIdConnectException('bad_issued_at');
        }

        return $claims;
    }

    /**
     * Check the ID token and return its claims
     *
     * @return Claims
     *
     * @throws OpenIdConnectException
     */
    private function validate(string $idToken, string $nonce, string $jwksUri): array
    {
        [$algorithm, $keyId] = $this->header($idToken, null);
        $claims = $this->verified($idToken, $algorithm, $keyId, $jwksUri);
        $this->checkClaims($claims, $nonce);

        return $claims;
    }

    /**
     * The shape and the header of a token: three segments, an algorithm of the list (`none`, every HMAC algorithm and
     * anything else are refused before any key is read) and, when a type is required, exactly that `typ`
     *
     * @return array{0: string, 1: string|null} The algorithm and the key id
     *
     * @throws OpenIdConnectException
     */
    private function header(string $token, ?string $type): array
    {
        $segments = explode('.', $token);
        if (count($segments) !== 3) {
            throw new OpenIdConnectException('bad_token');
        }

        $header = json_decode(JWT::urlsafeB64Decode($segments[0]), true, 4);
        $header = is_array($header) ? $header : [];
        $algorithm = $header['alg'] ?? null;
        if (! is_string($algorithm) || ! in_array($algorithm, self::ALGORITHMS, true)) {
            throw new OpenIdConnectException('unsupported_algorithm');
        }
        if ($type !== null && ($header['typ'] ?? null) !== $type) {
            throw new OpenIdConnectException('wrong_type');
        }
        $headerKeyId = $header['kid'] ?? null;

        return [$algorithm, is_string($headerKeyId) ? $headerKeyId : null];
    }

    /**
     * The claims of a token whose signature is valid for the key of its key id. With `$limitRefresh` (a call that anybody
     * can send, like the change call of #1386) the keys are read again for an unknown key id at most once a minute: the
     * calls of a stranger cannot make the server read the keys of the provider at the rate of their requests.
     *
     * @return Claims
     *
     * @throws OpenIdConnectException
     */
    private function verified(string $token, string $algorithm, ?string $keyId, string $jwksUri, bool $limitRefresh = false): array
    {
        [$jwk, $fresh] = $this->findKey($jwksUri, $algorithm, $keyId, false);
        if ($jwk === null && $keyId !== null && ! $fresh && (! $limitRefresh || Cache::add('oidc:jwks:refreshed:'.hash('sha256', $jwksUri), true, self::KEY_REFRESH_SECONDS))) {
            // An unknown key id: the provider may have rotated its keys. Read them again, once
            [$jwk] = $this->findKey($jwksUri, $algorithm, $keyId, true);
        }
        if ($jwk === null) {
            throw new OpenIdConnectException('unknown_key');
        }

        return $this->verifySignature($token, $jwk, $algorithm);
    }

    /**
     * @param  array<string, string>  $jwk
     * @return Claims
     *
     * @throws OpenIdConnectException
     */
    private function verifySignature(string $idToken, array $jwk, string $algorithm): array
    {
        $previousLeeway = JWT::$leeway;
        $previousTimestamp = JWT::$timestamp;
        try {
            // The skew applies to the date of issue only: exp is checked again without any skew below
            JWT::$leeway = self::CLOCK_SKEW_SECONDS;
            JWT::$timestamp = now()->getTimestamp();

            $key = JWK::parseKey($jwk, $algorithm);
            if (! $key instanceof Key || $key->getAlgorithm() !== $algorithm) {
                throw new OpenIdConnectException('unknown_key');
            }

            /** @var Claims $claims The claims of the token: each value is narrowed where it is read */
            $claims = get_object_vars(JWT::decode($idToken, $key));

            return $claims;
        } catch (SignatureInvalidException) {
            throw new OpenIdConnectException('bad_signature');
        } catch (ExpiredException) {
            throw new OpenIdConnectException('expired');
        } catch (BeforeValidException) {
            throw new OpenIdConnectException('not_yet_valid');
        } catch (OpenIdConnectException $e) {
            throw $e;
        } catch (Throwable) {
            throw new OpenIdConnectException('bad_token');
        } finally {
            JWT::$leeway = $previousLeeway;
            JWT::$timestamp = $previousTimestamp;
        }
    }

    /**
     * @param  Claims  $claims
     *
     * @throws OpenIdConnectException
     */
    private function checkClaims(array $claims, string $nonce): void
    {
        if (($claims['iss'] ?? null) !== $this->issuer()) {
            throw new OpenIdConnectException('wrong_issuer');
        }

        $audience = $claims['aud'] ?? null;
        $audiences = is_string($audience) ? [$audience] : (is_array($audience) ? array_values($audience) : []);
        if (! in_array($this->clientId(), $audiences, true)) {
            throw new OpenIdConnectException('wrong_audience');
        }
        // Several audiences: the token must say that it was issued to this client (OpenID Connect Core, 3.1.3.7)
        $authorizedParty = $claims['azp'] ?? null;
        if (($authorizedParty !== null || count($audiences) > 1) && $authorizedParty !== $this->clientId()) {
            throw new OpenIdConnectException('wrong_authorized_party');
        }

        $now = now()->getTimestamp();
        $expires = $claims['exp'] ?? null;
        if (! is_int($expires) && ! is_float($expires)) {
            throw new OpenIdConnectException('no_expiry');
        }
        if ($expires <= $now) {
            throw new OpenIdConnectException('expired');
        }
        $issuedAt = $claims['iat'] ?? null;
        if ((! is_int($issuedAt) && ! is_float($issuedAt)) || $issuedAt > $now + self::CLOCK_SKEW_SECONDS) {
            throw new OpenIdConnectException('bad_issued_at');
        }

        $tokenNonce = $claims['nonce'] ?? null;
        if (! is_string($tokenNonce) || $nonce === '' || ! hash_equals($nonce, $tokenNonce)) {
            throw new OpenIdConnectException('wrong_nonce');
        }
    }

    /**
     * The key for an algorithm and a key id, from the cache or from the provider
     *
     * @return array{0: array<string, string>|null, 1: bool} The key, and whether the keys were just read from the provider
     *
     * @throws OpenIdConnectException
     */
    private function findKey(string $jwksUri, string $algorithm, ?string $keyId, bool $refresh): array
    {
        [$keys, $fresh] = $this->keys($jwksUri, $refresh);

        $candidates = [];
        foreach ($keys as $jwk) {
            if (self::algorithmOf($jwk) !== $algorithm) {
                continue;
            }
            if ($keyId !== null) {
                if (($jwk['kid'] ?? null) === $keyId) {
                    $candidates[] = $jwk;
                }
            } else {
                $candidates[] = $jwk;
            }
        }

        // Without a key id, only a key set with exactly one fitting key can be used
        return [count($candidates) === 1 ? $candidates[0] : null, $fresh];
    }

    /**
     * The usable public keys of the provider: signature keys of a type that fits one of the algorithms
     *
     * @return array{0: list<array<string, string>>, 1: bool} The keys, and whether they were just read from the provider
     *
     * @throws OpenIdConnectException
     */
    private function keys(string $jwksUri, bool $refresh): array
    {
        $cacheKey = 'oidc:jwks:'.hash('sha256', $jwksUri);

        if (! $refresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                /** @var Json $cached What this class stored: the keys, cleaned again */
                $keys = $this->cleanKeys($cached);
                if ($keys !== []) {
                    return [$keys, false];
                }
            }
        }

        $document = $this->fetchJson($jwksUri);
        $list = $document['keys'] ?? null;
        $keys = is_array($list) ? $this->cleanKeys($list) : [];
        if ($keys === []) {
            throw new OpenIdConnectException('no_keys');
        }

        Cache::put($cacheKey, $keys, self::CACHE_SECONDS);

        return [$keys, true];
    }

    /**
     * Keep the public signature keys that the client can use, with the public members only (a private part or a shared
     * secret of the document is dropped): RSA of 2048 bits or more, P-256, Ed25519
     *
     * @param  Json  $list
     * @return list<array<string, string>>
     */
    private function cleanKeys(array $list): array
    {
        $keys = [];
        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }
            $jwk = [];
            foreach (self::JWK_FIELDS as $field) {
                $value = $item[$field] ?? null;
                if (is_string($value)) {
                    $jwk[$field] = $value;
                }
            }

            $algorithm = self::algorithmOf($jwk);
            if ($algorithm === null || (isset($jwk['use']) && $jwk['use'] !== 'sig')) {
                continue;
            }
            if ($algorithm === 'RS256' && self::rsaBits($jwk['n'] ?? '') < self::MIN_RSA_BITS) {
                continue;
            }
            $keys[] = $jwk;
        }

        return $keys;
    }

    /**
     * The algorithm that a key allows, from its type: never an HMAC (a key of type oct is not a signature key here)
     *
     * @param  array<string, string>  $jwk
     */
    private static function algorithmOf(array $jwk): ?string
    {
        $algorithm = match ($jwk['kty'] ?? null) {
            'RSA' => isset($jwk['n'], $jwk['e']) ? 'RS256' : null,
            'EC' => ($jwk['crv'] ?? null) === 'P-256' && isset($jwk['x'], $jwk['y']) ? 'ES256' : null,
            'OKP' => ($jwk['crv'] ?? null) === 'Ed25519' && isset($jwk['x']) ? 'EdDSA' : null,
            default => null,
        };

        // A key that names another algorithm is not used for this one
        return $algorithm !== null && (! isset($jwk['alg']) || $jwk['alg'] === $algorithm) ? $algorithm : null;
    }

    private static function rsaBits(string $modulus): int
    {
        $bytes = ltrim(JWT::urlsafeB64Decode($modulus), "\0");

        return strlen($bytes) * 8;
    }

    /**
     * GET a JSON document from the provider
     *
     * @return Json
     *
     * @throws OpenIdConnectException
     */
    private function fetchJson(string $url): array
    {
        try {
            $response = $this->request($url)->get($url);
        } catch (ConnectionException) {
            throw new OpenIdConnectException('provider_unreachable');
        }

        return $this->decode($response, 'provider_error');
    }

    /**
     * @return Json
     *
     * @throws OpenIdConnectException
     */
    private function decode(Response $response, string $reason): array
    {
        $body = $response->body();
        if ($response->status() !== 200 || strlen($body) > self::MAX_BODY_BYTES) {
            throw new OpenIdConnectException($reason);
        }
        $data = json_decode($body, true, 8);
        if (! is_array($data)) {
            throw new OpenIdConnectException($reason);
        }

        /** @var Json $data A JSON document: each value is narrowed where it is read */
        return $data;
    }

    /**
     * A request to the provider: a timeout, no redirect, a size limit, and for a public host the checked address
     * (SafeUrl). Only a loopback host in the local environment skips the check, to test with a provider on the machine.
     *
     * @throws OpenIdConnectException
     */
    private function request(string $url): PendingRequest
    {
        if (! OpenIdConnect::isAllowedAddress($url)) {
            throw new OpenIdConnectException('address_refused');
        }

        $options = [
            'allow_redirects' => false,
            'verify' => true,
            'progress' => static function (int $downloadTotal, int $downloaded): void {
                if ($downloadTotal > self::MAX_BODY_BYTES || $downloaded > self::MAX_BODY_BYTES) {
                    throw new OpenIdConnectException('provider_error');
                }
            },
        ];

        $host = (string) parse_url($url, PHP_URL_HOST);
        if (! (app()->isLocal() && OpenIdConnect::isLoopback($host))) {
            try {
                $options['curl'] = SafeUrl::pinnedResolveOption($url, SafeUrl::assertPublic($url));
            } catch (BusinessException) {
                throw new OpenIdConnectException('address_refused');
            }
        }

        return Http::withOptions($options)
            ->timeout(self::HTTP_TIMEOUT_SECONDS)
            ->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
            ->acceptJson();
    }

    private function issuer(): string
    {
        return OpenIdConnect::issuer() ?? throw new OpenIdConnectException('not_configured');
    }

    private function clientId(): string
    {
        return OpenIdConnect::clientId() ?? throw new OpenIdConnectException('not_configured');
    }

    private function clientSecret(): string
    {
        return OpenIdConnect::clientSecret() ?? throw new OpenIdConnectException('not_configured');
    }
}
