<?php

namespace App\Actions\Auth;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * A sign-up that waits for its link (ADR 0065): a random token, stored hashed in the cache, as a sign-in link. Its value
 * holds the name, the address and the landing (ADR 0057), encrypted with the key of the application: a row of the
 * database cache that waits for its removal holds no address in clear. It lives as long as the link of the address check
 * (`auth.verification.expire`, 60 minutes), and works once.
 */
final class PendingSignUps
{
    /**
     * Stores a pending sign-up and returns the token of its link
     */
    public static function start(string $name, #[\SensitiveParameter] string $email, ?string $landing): string
    {
        $token = Str::random(64);
        $value = Crypt::encryptString((string) json_encode(['name' => $name, 'email' => $email, 'landing' => $landing]));
        Cache::put(self::key($token), $value, now()->addMinutes(self::validityMinutes()));

        return $token;
    }

    /**
     * The pending sign-up of a token, without using it up, or null (an unknown token, a link that was used or expired)
     *
     * @return array{name: string, email: string, landing: string|null}|null
     */
    public static function peek(#[\SensitiveParameter] string $token): ?array
    {
        $value = Cache::get(self::key($token));

        return self::decode(is_string($value) ? $value : null);
    }

    /**
     * The pending sign-up of a token, read and removed at once, so that a link works once, or null
     *
     * @return array{name: string, email: string, landing: string|null}|null
     */
    public static function pull(#[\SensitiveParameter] string $token): ?array
    {
        $value = Cache::pull(self::key($token));

        return self::decode(is_string($value) ? $value : null);
    }

    /**
     * The life of the link, in minutes: the one of the link of the address check
     */
    public static function validityMinutes(): int
    {
        $minutes = Config::get('auth.verification.expire', 60);

        return is_int($minutes) && $minutes > 0 ? $minutes : 60;
    }

    /**
     * @return array{name: string, email: string, landing: string|null}|null
     */
    private static function decode(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        try {
            $entry = json_decode(Crypt::decryptString($value), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($entry) || ! is_string($entry['name'] ?? null) || ! is_string($entry['email'] ?? null)) {
            return null;
        }
        $landing = $entry['landing'] ?? null;

        return ['name' => $entry['name'], 'email' => $entry['email'], 'landing' => is_string($landing) ? $landing : null];
    }

    private static function key(string $token): string
    {
        return 'sign-up:'.hash('sha256', $token);
    }
}
