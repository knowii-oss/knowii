<?php

namespace App\Support;

use App\Contracts\Realtime\EndsLiveConnections;
use GuzzleHttp\Client as GuzzleClient;
use Pusher\Pusher;

/**
 * Ends the live connections of an account on Reverb, through the Pusher HTTP API (`POST .../users/{id}/terminate_connections`,
 * ADR 0059, ADR 0075). Reverb closes each connection whose user is the account: the authorization of every channel is
 * signed with the account (App\Broadcasting\AccountBoundBroadcaster), and Reverb keeps it on the subscription. Another
 * broadcaster (log, null, a hosted service) gets no call.
 *
 * The call runs in the job App\Jobs\EndLiveConnections, after the commit of the change: it waits TIMEOUT_SECONDS at most,
 * to connect and to get the answer. A server that does not answer in time, or answers with an error, throws: the job logs
 * it and tries again.
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

        $this->client('broadcasting.connections.'.$connection)->terminateUserConnections($userCuid);
    }

    /**
     * A client of the HTTP API of Reverb with the configuration of the connection, and the short timeout of this call. A
     * configuration without its keys throws, and the job writes the line of a failure
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
