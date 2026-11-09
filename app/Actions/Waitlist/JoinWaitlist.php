<?php

namespace App\Actions\Waitlist;

use App\Constants;
use App\Contracts\Waitlist\JoinsWaitlist;
use App\Exceptions\TechnicalException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Adds an email address to the waitlist (an EmailOctopus list).
 *
 * Protection against bots and abuse:
 * - Cloudflare Turnstile token, verified server-side (required: without Turnstile keys, nothing is accepted)
 * - honeypot field: a hidden field that people never fill in
 * - rate limit per IP address (route middleware, see AppServiceProvider)
 * - double opt-in: by default the contact is "pending" until the address is confirmed
 */
class JoinWaitlist implements JoinsWaitlist
{
    private const int HTTP_TIMEOUT_SECONDS = 10;

    /**
     * @param  array<mixed>  $input
     *
     * @throws ValidationException|TechnicalException
     */
    final public function join(array $input, ?string $ipAddress): void
    {
        Log::info('Processing a waitlist request');

        $validator = Validator::make($input, [
            // WARNING: Those rules must remain aligned with the client-side waitlistSchema
            'email' => ['required', 'string', 'email:rfc', 'max:'.Constants::MAX_LENGTH_WAITLIST_EMAIL],
            'turnstileToken' => ['required', 'string', 'max:'.Constants::MAX_LENGTH_TURNSTILE_TOKEN],
            // Honeypot: must stay empty
            Constants::WAITLIST_HONEYPOT_FIELD => ['nullable', 'string', 'max:255'],
        ]);

        $validator->validate();

        /** @var array{email: string, turnstileToken: string, website?: string|null} $validated */
        $validated = $validator->validated();

        if (filled($validated[Constants::WAITLIST_HONEYPOT_FIELD] ?? null)) {
            // Answer as if it worked, so bots learn nothing
            Log::notice('Waitlist request ignored: the honeypot field is filled');

            return;
        }

        $apiKey = config('services.emailoctopus.api_key');
        $listId = config('services.emailoctopus.waitlist_list_id');
        if (! is_string($apiKey) || $apiKey === '' || ! is_string($listId) || $listId === '') {
            Log::error('Waitlist request refused: EmailOctopus is not configured (EMAILOCTOPUS_API_KEY, EMAILOCTOPUS_WAITLIST_LIST_ID)');
            throw new TechnicalException('The waitlist is not available right now. Please try again later.');
        }

        $this->verifyTurnstileToken($validated['turnstileToken'], $ipAddress);

        $this->addContact($apiKey, $listId, $validated['email']);
    }

    /**
     * Verify the Turnstile token with Cloudflare. Fails closed: without a secret key, nothing is accepted.
     *
     * @throws ValidationException|TechnicalException
     */
    private function verifyTurnstileToken(string $token, ?string $ipAddress): void
    {
        $secretKey = config('services.turnstile.secret_key');
        $verifyUrl = config('services.turnstile.verify_url');
        if (! is_string($secretKey) || $secretKey === '' || ! is_string($verifyUrl)) {
            Log::error('Waitlist request refused: Cloudflare Turnstile is not configured (TURNSTILE_SECRET_KEY)');
            throw new TechnicalException('The waitlist is not available right now. Please try again later.');
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
            Log::notice('Waitlist request refused: invalid Turnstile token', ['errorCodes' => $response->json('error-codes')]);
            throw ValidationException::withMessages([
                'turnstileToken' => 'The bot check failed. Please try again.',
            ]);
        }
    }

    /**
     * @throws ValidationException|TechnicalException
     */
    private function addContact(string $apiKey, string $listId, string $email): void
    {
        $baseUrl = config('services.emailoctopus.base_url');
        $status = config('services.emailoctopus.waitlist_contact_status');

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(rtrim(is_string($baseUrl) ? $baseUrl : '', '/').'/lists/'.rawurlencode($listId).'/contacts', [
                    'email_address' => $email,
                    'status' => in_array($status, ['pending', 'subscribed'], true) ? $status : 'pending',
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Could not reach EmailOctopus', ['exception' => $e]);
            throw new TechnicalException('The waitlist is not available right now. Please try again later.', null, $e);
        }

        $this->handleEmailOctopusResponse($response);
    }

    /**
     * @throws ValidationException|TechnicalException
     */
    private function handleEmailOctopusResponse(Response $response): void
    {
        if ($response->successful()) {
            Log::info('Contact added to the waitlist');

            return;
        }

        if ($response->status() === 409) {
            // Already on the list: same answer as a new signup, so the API does not reveal who is on the list
            Log::info('Contact already on the waitlist');

            return;
        }

        if ($response->status() === 422) {
            Log::notice('EmailOctopus refused the email address', ['errors' => $response->json('errors')]);
            throw ValidationException::withMessages([
                'email' => 'Please enter a valid email address.',
            ]);
        }

        Log::error('EmailOctopus request failed', ['status' => $response->status(), 'body' => $response->json('detail')]);
        throw new TechnicalException('The waitlist is not available right now. Please try again later.');
    }
}
