<?php

namespace App\Contracts\Realtime;

interface ReadsPresenceChannels
{
    /**
     * The identifiers (user cuids) of the members in a presence channel now, for example "community.abc.online".
     * Empty when the WebSocket server cannot answer.
     *
     * @return list<string>
     */
    public function memberCuids(string $channel): array;
}
