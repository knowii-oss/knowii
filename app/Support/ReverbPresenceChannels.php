<?php

namespace App\Support;

use App\Contracts\Realtime\ReadsPresenceChannels;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks the WebSocket server (Reverb, through the Pusher HTTP API) who is in a presence channel (#1187, ADR 0027).
 * Members who hide their online status read the lists this way: they never join the channels.
 */
class ReverbPresenceChannels implements ReadsPresenceChannels
{
    final public function memberCuids(string $channel): array
    {
        $broadcaster = Broadcast::connection();
        if (! $broadcaster instanceof PusherBroadcaster) {
            // The log and null drivers (tests, instances without real time) have no channels
            return [];
        }

        try {
            $answer = $broadcaster->getPusher()->getPresenceUsers('presence-'.$channel);
        } catch (Throwable $e) {
            Log::warning('The WebSocket server did not list the members of a presence channel', ['channel' => $channel, 'error' => $e->getMessage()]);

            return [];
        }

        $users = isset($answer->users) && is_array($answer->users) ? $answer->users : [];

        return array_values(array_filter(array_map(
            static fn (mixed $user) => is_object($user) && isset($user->id) && is_string($user->id) ? $user->id : null,
            $users,
        )));
    }
}
