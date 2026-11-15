<?php

namespace App\Actions\Entitlements;

use App\Actions\Auth\OpenIdConnectClient;
use App\Contracts\Entitlements\ReceivesEntitlementEvents;
use App\Exceptions\OpenIdConnectException;
use App\Support\OpenIdConnect;
use Illuminate\Support\Facades\Cache;

/**
 * The change call of the provider (#1386, ADR 0047): a compact JWS signed with a key of the `jwks_uri` of the configured
 * issuer. It is accepted only when, in this order:
 *
 * 1. the algorithm is one of the list of the client of #1385 (never `none`, never an HMAC: a public key cannot be used as a
 *    secret), the header `typ` is `entitlements-event+jwt`, the key is found by `kid` and the signature is valid;
 * 2. `iss` is the issuer, `aud` is the client ID, `exp` is in the future, `iat` is at most 5 minutes old (and not in the
 *    future);
 * 3. `event` is `entitlements.changed` and `sub` is a plain subject;
 * 4. `jti` is present and not seen in the last 24 hours. A `jti` is kept only after every check above passed, so that a
 *    refused call can never use up the `jti` of a real one. The store is the cache, which forgets it on its own.
 *
 * Nothing else of the call is read.
 */
final class ReceiveEntitlementEvent implements ReceivesEntitlementEvents
{
    public const string TYPE = 'entitlements-event+jwt';

    public const string EVENT = 'entitlements.changed';

    public const int MAX_AGE_SECONDS = 300;

    public const int JTI_HOURS = 24;

    private const int MAX_CLAIM_LENGTH = 255;

    public function __construct(private readonly OpenIdConnectClient $client) {}

    final public function receive(string $jws): ?string
    {
        $claims = $this->client->verifySignedEvent($jws, self::TYPE, self::MAX_AGE_SECONDS);

        if (($claims['event'] ?? null) !== self::EVENT) {
            throw new OpenIdConnectException('wrong_event');
        }
        $subject = $claims['sub'] ?? null;
        $jti = $claims['jti'] ?? null;
        if (! is_string($subject) || ! is_string($jti) || ! self::isPlain($subject) || ! self::isPlain($jti)) {
            throw new OpenIdConnectException('bad_claims');
        }

        // The issuer is known to be the configured one (checked above): the key is the pair issuer and jti
        $seen = ! Cache::add('oidc:entitlements:jti:'.hash('sha256', (OpenIdConnect::issuer() ?? '').'|'.$jti), true, now()->addHours(self::JTI_HOURS));

        return $seen ? null : $subject;
    }

    /**
     * A subject or an event id: 1 to 255 characters, no control character
     */
    private static function isPlain(string $value): bool
    {
        return $value !== '' && strlen($value) <= self::MAX_CLAIM_LENGTH && preg_match('/[\x00-\x1f\x7f]/', $value) !== 1;
    }
}
