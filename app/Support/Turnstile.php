<?php

namespace App\Support;

use App\Exceptions\TechnicalException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Cloudflare Turnstile, the bot check of public forms (waitlist #1171, sign-up #737). The server verifies every token:
 * the widget alone protects nothing.
 */
final class Turnstile
{
    private const int HTTP_TIMEOUT_SECONDS = 10;

    /**
     * Whether the instance has Turnstile keys (both the site key for the widget and the secret key for the check).
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    public static function siteKey(): ?string
    {
        $key = config('services.turnstile.site_key');

        return self::isConfigured() && is_string($key) ? $key : null;
    }

    /**
     * Verify a token with Cloudflare. Fails closed: without the secret key, nothing is accepted.
     *
     * @param  string  $field  The form field that gets the error when the check fails
     *
     * @throws ValidationException When Cloudflare refuses the token
     * @throws TechnicalException When Turnstile is not configured or cannot be reached
     */
    public static function verify(?string $token, ?string $ipAddress, string $field): void
    {
        $secretKey = config('services.turnstile.secret_key');
        $verifyUrl = config('services.turnstile.verify_url');
        if (! is_string($secretKey) || $secretKey === '' || ! is_string($verifyUrl)) {
            Log::error('Request refused: Cloudflare Turnstile is not configured (TURNSTILE_SECRET_KEY)');
            throw new TechnicalException('This form is not available right now. Please try again later.');
        }

        if ($token === null || $token === '') {
            throw ValidationException::withMessages([$field => 'Complete the bot check, then try again.']);
        }

        try {
            $response = Http::asForm()
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($verifyUrl, array_filter([
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $ipAddress,
                ]));
        } catch (ConnectionException $e) {
            Log::warning('Could not reach Cloudflare Turnstile', ['exception' => $e]);
            throw new TechnicalException('The bot check is not available right now. Please try again later.', null, $e);
        }

        if (! $response->successful()) {
            Log::warning('Cloudflare Turnstile verification failed', ['status' => $response->status()]);
            throw new TechnicalException('The bot check is not available right now. Please try again later.');
        }

        if ($response->json('success') !== true) {
            Log::notice('Request refused: invalid Turnstile token', ['errorCodes' => $response->json('error-codes')]);
            throw ValidationException::withMessages([$field => 'The bot check failed. Please try again.']);
        }
    }
}
