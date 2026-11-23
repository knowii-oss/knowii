<?php

namespace App\Actions\Mail;

use App\Contracts\Mail\RemovesProviderSuppressions;
use App\Support\MailSettings;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Resend\Client;
use Resend\Exceptions\ErrorException;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use Throwable;

/**
 * Takes an address off the suppression list of Resend (#1478, ADR 0054): `DELETE /suppressions/{email}` of the SDK
 * (`$client->suppressions->remove()`), only for the mailer `resend` with a key. The client has a timeout of 10 seconds,
 * like ResendMailUsage, so that a provider that does not answer cannot hold a request or a worker for long. The key needs
 * the right to manage the list (full access). Any failure gives false and one warning with the error type of the provider,
 * never the address, the key or the message.
 */
final class RemoveResendSuppression implements RemovesProviderSuppressions
{
    private const int TIMEOUT_SECONDS = 10;

    /**
     * @param  (callable(RequestInterface, array<array-key, scalar|array<array-key, scalar|null>|object|null>): PromiseInterface)|null  $handler  The HTTP handler of the client, for the tests: they never call the network
     */
    public function __construct(private $handler = null) {}

    final public function remove(string $address): bool
    {
        $key = MailSettings::resendKey();
        if (! MailSettings::sendsThroughResend() || $key === null) {
            return false;
        }

        try {
            // The address is a path segment of the call: encoded, so that no character of it changes the path or the query
            $this->client($key)->suppressions->remove(rawurlencode($address));
        } catch (Throwable $exception) {
            Log::warning('An address could not be taken off the list of the mail provider', ['exception' => $exception::class, 'error_type' => self::errorType($exception)]);

            return false;
        }

        return true;
    }

    private static function errorType(Throwable $exception): ?string
    {
        if (! $exception instanceof ErrorException) {
            return null;
        }

        try {
            return $exception->getErrorType();
        } catch (Throwable) {
            return null;
        }
    }

    private function client(string $key): Client
    {
        $baseUri = getenv('RESEND_BASE_URL');
        $options = ['timeout' => self::TIMEOUT_SECONDS, 'connect_timeout' => self::TIMEOUT_SECONDS];
        if ($this->handler !== null) {
            $options['handler'] = $this->handler;
        }

        return new Client(new HttpTransporter(
            new GuzzleClient($options),
            BaseUri::from(is_string($baseUri) && $baseUri !== '' ? $baseUri : 'api.resend.com'),
            Headers::withAuthorization(ApiKey::from($key)),
        ));
    }
}
