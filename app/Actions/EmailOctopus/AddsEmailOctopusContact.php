<?php

namespace App\Actions\EmailOctopus;

use App\Constants;
use App\Exceptions\TechnicalException;
use App\Support\Turnstile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Adds an email address to an EmailOctopus list: the waitlist and the newsletter (#938) share this code.
 *
 * Protection against bots and abuse:
 * - Cloudflare Turnstile token, verified server-side (required: without Turnstile keys, nothing is accepted)
 * - honeypot field: a hidden field that people never fill in
 * - rate limit per IP address (route middleware, see AppServiceProvider)
 * - double opt-in: by default the contact is "pending" until the address is confirmed
 */
abstract class AddsEmailOctopusContact
{
    private const int HTTP_TIMEOUT_SECONDS = 10;

    /**
     * The name of the list in messages and logs ("waitlist", "newsletter")
     */
    abstract protected function listName(): string;

    /**
     * The key of the list ID in config/services.php (for example "services.emailoctopus.waitlist_list_id")
     */
    abstract protected function listIdConfigKey(): string;

    /**
     * The key of the contact status in config/services.php: "pending" (double opt-in) or "subscribed"
     */
    abstract protected function contactStatusConfigKey(): string;

    /**
     * @param  array<mixed>  $input
     *
     * @throws ValidationException|TechnicalException
     */
    final protected function addContact(array $input, ?string $ipAddress): void
    {
        Log::info('Processing a '.$this->listName().' request');

        $validator = Validator::make($input, [
            // WARNING: Those rules must remain aligned with the client-side waitlistEmailSchema
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
            Log::notice(ucfirst($this->listName()).' request ignored: the honeypot field is filled');

            return;
        }

        $apiKey = config('services.emailoctopus.api_key');
        $listId = config($this->listIdConfigKey());
        if (! is_string($apiKey) || $apiKey === '' || ! is_string($listId) || $listId === '') {
            Log::error(ucfirst($this->listName()).' request refused: EmailOctopus is not configured', ['list' => $this->listIdConfigKey()]);
            throw new TechnicalException($this->unavailableMessage());
        }

        Turnstile::verify($validated['turnstileToken'], $ipAddress, 'turnstileToken');

        $this->send($apiKey, $listId, $validated['email']);
    }

    private function unavailableMessage(): string
    {
        return 'The '.$this->listName().' is not available right now. Please try again later.';
    }

    /**
     * @throws ValidationException|TechnicalException
     */
    private function send(string $apiKey, string $listId, string $email): void
    {
        $baseUrl = config('services.emailoctopus.base_url');
        $status = config($this->contactStatusConfigKey());

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
            throw new TechnicalException($this->unavailableMessage(), null, $e);
        }

        $this->handleResponse($response);
    }

    /**
     * @throws ValidationException|TechnicalException
     */
    private function handleResponse(Response $response): void
    {
        if ($response->successful()) {
            Log::info('Contact added to the '.$this->listName());

            return;
        }

        if ($response->status() === 409) {
            // Already on the list: same answer as a new signup, so the API does not reveal who is on the list
            Log::info('Contact already on the '.$this->listName());

            return;
        }

        if ($response->status() === 422) {
            Log::notice('EmailOctopus refused the email address', ['errors' => $response->json('errors')]);
            throw ValidationException::withMessages([
                'email' => 'Please enter a valid email address.',
            ]);
        }

        Log::error('EmailOctopus request failed', ['status' => $response->status(), 'body' => $response->json('detail')]);
        throw new TechnicalException($this->unavailableMessage());
    }
}
