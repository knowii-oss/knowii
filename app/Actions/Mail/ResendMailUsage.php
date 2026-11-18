<?php

namespace App\Actions\Mail;

use App\Contracts\Mail\ReadsMailUsage;
use App\Support\MailSettings;
use App\Support\MailUsage;
use App\Support\MailUsagePeriod;
use Carbon\CarbonImmutable;
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
 * Reads the use of the team at Resend (#1477, ADR 0054): `GET /usage` of the SDK (`$client->usage->get()`), the
 * `emails.monthly` and `emails.daily` parts with `used`, `limit` and `resets_at`. It runs only for the mailer `resend`
 * with a key. The client is the one of `Resend::client()` with a timeout, so that a provider that does not answer cannot
 * hold the scheduler. Any failure (network, a key without the right to read the usage, an answer that is not as
 * documented) gives null and one warning: the check then uses the count of this instance.
 */
final class ResendMailUsage implements ReadsMailUsage
{
    private const int TIMEOUT_SECONDS = 10;

    /**
     * @param  (callable(RequestInterface, array<array-key, scalar|array<array-key, scalar|null>|object|null>): PromiseInterface)|null  $handler  The HTTP handler of the client, for the tests: they never call the network
     */
    public function __construct(private $handler = null) {}

    final public function read(): ?MailUsage
    {
        $key = MailSettings::resendKey();
        if (! MailSettings::readsProviderUsage() || $key === null) {
            return null;
        }

        try {
            /** @var array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|null>|null>|null> $data A JSON document: each value is narrowed where it is read */
            $data = $this->client($key)->usage->get()->toArray();
            $usage = self::fromArray($data);
        } catch (Throwable $exception) {
            // The error type of the provider tells a key without the right to read the usage from an outage. It holds no
            // secret; the message of the exception is not logged
            Log::warning('The usage of the mail provider could not be read: the count of this instance is used', ['exception' => $exception::class, 'error_type' => self::errorType($exception)]);

            return null;
        }

        if ($usage === null) {
            Log::warning('The usage of the mail provider is not as documented: the count of this instance is used');
        }

        return $usage;
    }

    /**
     * The usage from the decoded answer, or null when the monthly part is not as documented. The daily part is null when
     * the provider gives none.
     *
     * @param  array<array-key, scalar|array<array-key, scalar|array<array-key, scalar|null>|null>|null>  $data
     */
    public static function fromArray(array $data): ?MailUsage
    {
        $emails = $data['emails'] ?? null;
        if (! is_array($emails)) {
            return null;
        }

        $monthly = self::period($emails['monthly'] ?? null);
        if ($monthly === null) {
            return null;
        }

        return new MailUsage($monthly, self::period($emails['daily'] ?? null));
    }

    /**
     * @param  array<array-key, scalar|array<array-key, scalar|null>|null>|scalar|null  $part
     */
    private static function period(array|int|float|string|bool|null $part): ?MailUsagePeriod
    {
        if (! is_array($part) || ! is_int($part['used'] ?? null) || $part['used'] < 0) {
            return null;
        }

        $limit = $part['limit'] ?? null;
        $resetsAt = $part['resets_at'] ?? null;
        try {
            $resetsAt = is_string($resetsAt) && $resetsAt !== '' ? CarbonImmutable::parse($resetsAt)->utc() : null;
        } catch (Throwable) {
            $resetsAt = null;
        }

        return new MailUsagePeriod($part['used'], is_int($limit) && $limit > 0 ? $limit : null, $resetsAt);
    }

    private static function errorType(Throwable $exception): ?string
    {
        if (! $exception instanceof ErrorException) {
            return null;
        }

        try {
            return $exception->getErrorType();
        } catch (Throwable) {
            // An error of the provider without a type or a name
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
