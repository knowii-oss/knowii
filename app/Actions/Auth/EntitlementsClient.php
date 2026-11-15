<?php

namespace App\Actions\Auth;

use App\Constants;
use App\Contracts\Auth\ReadsSubjectEntitlements;
use App\Exceptions\EntitlementsReadException;
use App\Exceptions\OpenIdConnectException;
use App\Support\OidcEntitlements;
use App\Support\OpenIdConnect;
use App\Support\SubjectEntitlements;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Reads the entitlements of one subject at the sign-in provider (#1386, ADR 0047): `GET <OIDC_ENTITLEMENTS_URL>` with a
 * token of the client credentials grant (the scope `entitlements:read:subjects` and the optional resource). The requests
 * go through the client of #1385 (`OpenIdConnectClient`): its discovery, its safe requests (timeout, no redirect, size
 * limit, public address only), its TLS rules.
 *
 * - The token is kept in the cache, encrypted, until a minute before it expires, and is dropped when the provider refuses
 *   it (401): one new token is asked once. It is never logged.
 * - 200 gives the active keys (`entitlements`). A body that is not what the provider documents is never read as "no keys":
 *   it is a temporary failure, so that a bad answer cannot take access away.
 * - 404 (the link at the provider is gone, or the account there is deleted) is its own answer: `gone`.
 * - A network error, a timeout, a 5xx, a 429 and any other status are temporary failures. A 401 or 403 after a fresh token
 *   is a configuration problem.
 */
final class EntitlementsClient implements ReadsSubjectEntitlements
{
    /**
     * The token is not kept for its last minute
     */
    private const int TOKEN_MARGIN_SECONDS = 60;

    private const int MAX_BODY_BYTES = 262144;

    public function __construct(private readonly OpenIdConnectClient $client) {}

    final public function read(string $subject): SubjectEntitlements
    {
        $url = OidcEntitlements::urlFor($subject);
        if ($url === null) {
            throw new EntitlementsReadException('not_configured', false);
        }

        $response = $this->get($url, false);
        if ($response->status() === 401) {
            // The provider may have stopped the token early: one fresh token, once
            $response = $this->get($url, true);
        }

        $status = $response->status();
        if ($status === 404) {
            return SubjectEntitlements::gone();
        }
        if ($status === 401 || $status === 403) {
            throw new EntitlementsReadException('provider_refused_client', false);
        }
        if ($status !== 200) {
            throw new EntitlementsReadException('provider_status', true);
        }

        return SubjectEntitlements::found($this->keysOf($response, $subject));
    }

    /**
     * @throws EntitlementsReadException
     */
    private function get(string $url, bool $freshToken): Response
    {
        $token = $this->token($freshToken);

        try {
            return $this->client->authorizedGet($url, $token);
        } catch (OpenIdConnectException $e) {
            throw new EntitlementsReadException($e->reason, ! in_array($e->reason, ['address_refused', 'not_configured'], true));
        }
    }

    /**
     * The keys of a 200 answer: valid, unique, sorted. The body must name this subject and hold a list of texts.
     *
     * @return list<string>
     *
     * @throws EntitlementsReadException
     */
    private function keysOf(Response $response, string $subject): array
    {
        $body = $response->body();
        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new EntitlementsReadException('body_too_large', true);
        }
        $data = json_decode($body, true, 8);
        if (! is_array($data)) {
            throw new EntitlementsReadException('bad_body', true);
        }

        /** @var array<array-key, scalar|array<array-key, scalar|null>|null> $data A JSON document: each value is narrowed where it is read */
        $list = $data['entitlements'] ?? null;
        // The answer is about the subject that we asked for (when it says whose it is)
        if (! is_array($list) || (array_key_exists('sub', $data) && $data['sub'] !== $subject)) {
            throw new EntitlementsReadException('bad_body', true);
        }

        $keys = [];
        foreach ($list as $key) {
            // A key that a rule could not name (not the safe pattern) cannot give anything: it is ignored
            if (is_string($key) && OidcEntitlements::isValidKey($key)) {
                $keys[$key] = $key;
            }
        }
        $keys = array_values($keys);
        sort($keys);

        return array_slice($keys, 0, Constants::MAX_ENTITLEMENTS_PER_USER);
    }

    /**
     * The access token of the client: from the cache, or a new one from the token endpoint
     *
     * @throws EntitlementsReadException
     */
    private function token(bool $fresh): string
    {
        $cacheKey = 'oidc:entitlements:token:'.hash('sha256', implode('|', [OpenIdConnect::issuer() ?? '', OpenIdConnect::clientId() ?? '', OidcEntitlements::scope(), OidcEntitlements::resource() ?? '']));

        if ($fresh) {
            Cache::forget($cacheKey);
        } else {
            $cached = Cache::get($cacheKey);
            if (is_string($cached)) {
                try {
                    return Crypt::decryptString($cached);
                } catch (Throwable) {
                    // A value that cannot be read is replaced
                    Cache::forget($cacheKey);
                }
            }
        }

        try {
            $issued = $this->client->clientCredentialsToken(OidcEntitlements::scope(), OidcEntitlements::resource());
        } catch (OpenIdConnectException $e) {
            $status = $e->httpStatus;
            // A refusal of the client (400, 401, 403) is a configuration problem. A provider that is down, a limit and an
            // unreachable network are temporary
            $temporary = $status === null ? $e->reason !== 'not_configured' && $e->reason !== 'address_refused' : ($status >= 500 || $status === 429 || $status === 408);
            throw new EntitlementsReadException($e->reason, $temporary);
        }

        $ttl = $issued['expires_in'] - self::TOKEN_MARGIN_SECONDS;
        if ($ttl > 0) {
            Cache::put($cacheKey, Crypt::encryptString($issued['token']), $ttl);
        }

        return $issued['token'];
    }
}
