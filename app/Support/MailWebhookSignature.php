<?php

namespace App\Support;

use App\Constants;
use App\Exceptions\MailWebhookException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * The check of a call of the webhook of the mail provider (#1478, ADR 0054). Resend signs its calls with Svix (the
 * Standard Webhooks scheme): the headers `svix-id`, `svix-timestamp` (Unix seconds) and `svix-signature` (a list of
 * `version,signature` items separated by spaces), and the signature `v1` is the base64 of an HMAC-SHA256 of
 * "id.timestamp.raw body" with the secret.
 *
 * The platform checks it itself, with these rules, and the provider library is not used for it:
 *
 * - The secret is `whsec_` and strict base64 that decodes to a value that is not empty. Any other value means that the
 *   instance has no webhook (secret() is null): the route answers 404 and checks nothing. A value that is set but cannot
 *   be used writes one warning a day.
 * - A missing or empty header, an id that is not a short token, a timestamp that is not a whole number of seconds, or a
 *   signature header without any `version,signature` item: 401 (MailWebhookException), never 5xx.
 * - A time more than Constants::MAIL_WEBHOOK_TOLERANCE_SECONDS in the past or in the future: 401. The clock of the
 *   server must be right (NTP).
 * - Any `v1` item may match. The compare runs in constant time on the base64 text. No `v1` item that matches: 401.
 *
 * The raw body is signed: a body that is parsed and encoded again does not match.
 */
final class MailWebhookSignature
{
    public const string HEADER_ID = 'svix-id';

    public const string HEADER_TIMESTAMP = 'svix-timestamp';

    public const string HEADER_SIGNATURE = 'svix-signature';

    private const string SECRET_PREFIX = 'whsec_';

    // Base64 with its padding, and nothing else (no white space, no other alphabet)
    private const string STRICT_BASE64 = '/^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?$/';

    // The id of an event is a short token: it is stored, so its length is limited by the column
    private const string EVENT_ID = '/^[A-Za-z0-9._:\-]{1,255}$/';

    // At most 10 digits: a Unix time in seconds
    private const string TIMESTAMP = '/^\d{1,10}$/';

    private const string WARNING_CACHE_KEY = 'mail-webhook:unusable-secret-warned';

    /**
     * The key of the HMAC, or null when the instance has no usable secret. A secret that is set but cannot be used writes
     * one warning a day
     */
    public static function secret(): ?string
    {
        $secret = config('services.resend.webhook_secret');
        if (! is_string($secret) || trim($secret) === '') {
            return null;
        }

        $key = self::keyOf(trim($secret));
        if ($key === null && Cache::add(self::WARNING_CACHE_KEY, true, now()->addDay())) {
            Log::warning('The webhook secret of the mail provider cannot be used: it must be "whsec_" and base64. The webhook answers 404 until it is fixed');
        }

        return $key;
    }

    /**
     * Checks a call and gives the id of its event
     *
     * @param  string  $key  The key from secret()
     * @param  string  $payload  The raw body of the request
     *
     * @throws MailWebhookException
     */
    public static function verify(string $key, string $payload, ?string $id, ?string $timestamp, ?string $signature): string
    {
        if ($id === null || preg_match(self::EVENT_ID, $id) !== 1) {
            throw new MailWebhookException('bad_id', Response::HTTP_UNAUTHORIZED);
        }
        if ($timestamp === null || preg_match(self::TIMESTAMP, $timestamp) !== 1) {
            throw new MailWebhookException('bad_timestamp', Response::HTTP_UNAUTHORIZED);
        }
        $items = self::signatureItems($signature);
        if ($items === []) {
            throw new MailWebhookException('bad_signature_header', Response::HTTP_UNAUTHORIZED);
        }

        if (abs(CarbonImmutable::now()->getTimestamp() - (int) $timestamp) > Constants::MAIL_WEBHOOK_TOLERANCE_SECONDS) {
            throw new MailWebhookException('outside_the_tolerance', Response::HTTP_UNAUTHORIZED);
        }

        $expected = base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$payload, $key, true));
        $found = false;
        foreach ($items as [$version, $value]) {
            // Every item is compared, so that the time does not tell which one matched
            if ($version === 'v1' && hash_equals($expected, $value)) {
                $found = true;
            }
        }
        if (! $found) {
            throw new MailWebhookException('no_matching_signature', Response::HTTP_UNAUTHORIZED);
        }

        return $id;
    }

    /**
     * The key of a secret: `whsec_` and strict base64 that decodes to a value that is not empty, else null
     */
    private static function keyOf(string $secret): ?string
    {
        if (! str_starts_with($secret, self::SECRET_PREFIX)) {
            return null;
        }
        $encoded = substr($secret, strlen(self::SECRET_PREFIX));
        if ($encoded === '' || preg_match(self::STRICT_BASE64, $encoded) !== 1) {
            return null;
        }
        $key = base64_decode($encoded, true);

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * The items `version,signature` of the header, each split at its first comma. An item without a comma, or with an empty
     * part, is left out
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function signatureItems(?string $header): array
    {
        if ($header === null) {
            return [];
        }

        $items = [];
        foreach (preg_split('/ +/', trim($header)) ?: [] as $item) {
            $parts = explode(',', $item, 2);
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $items[] = [$parts[0], $parts[1]];
            }
        }

        return $items;
    }
}
