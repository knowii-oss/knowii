<?php

namespace App\Support;

use App\Contracts\Realtime\EndsLiveConnections;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;
use Throwable;

/**
 * Ends the live connections of an account on Reverb, through the Pusher HTTP API (`POST .../users/{id}/terminate_connections`,
 * ADR 0059). Reverb closes each connection whose user is the account: a connection learns its user when it joins a presence
 * channel (#1187, ADR 0027). Another broadcaster (log, null, a hosted service) gets no call.
 *
 * The call runs after the commit of the takeover, in the request of the person who proved the address: it waits
 * TIMEOUT_SECONDS at most, to connect and to get the answer. A server that does not answer in time, or answers with an
 * error, is one line of the log, never an error of the request.
 */
class ReverbLiveConnections implements EndsLiveConnections
{
    public const int TIMEOUT_SECONDS = 2;

    /**
     * @param  array<string, object>  $guzzleOptions  More options of the HTTP client (a test gives its handler here)
     */
    public function __construct(private readonly array $guzzleOptions = []) {}

    final public function endFor(string $userCuid): void
    {
        $connection = config('broadcasting.default');
        if (! is_string($connection) || config('broadcasting.connections.'.$connection.'.driver') !== 'reverb') {
            return;
        }

        try {
            $this->client('broadcasting.connections.'.$connection)->terminateUserConnections($userCuid);
        } catch (Throwable $e) {
            // The takeover is stored already: a broadcaster that does not answer changes nothing of it
            Log::warning('The WebSocket server did not end the connections of an account', ['user' => $userCuid, 'error' => $e::class]);
        }
    }

    /**
     * A client of the HTTP API of Reverb with the configuration of the connection, and the short timeout of this call. A
     * configuration without its keys throws, and the caller writes the line of a failure
     */
    private function client(string $config): Pusher
    {
        return new Pusher(
            config()->string($config.'.key'),
            config()->string($config.'.secret'),
            config()->string($config.'.app_id'),
            [...config()->array($config.'.options', []), 'timeout' => self::TIMEOUT_SECONDS],
            new GuzzleClient([...config()->array($config.'.client_options', []), ...$this->guzzleOptions, 'connect_timeout' => self::TIMEOUT_SECONDS, 'timeout' => self::TIMEOUT_SECONDS]),
        );
    }
}
