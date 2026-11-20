<?php

namespace App\Contracts\Realtime;

interface EndsLiveConnections
{
    /**
     * Ends the live connections (WebSockets) of the account on the broadcaster of the instance, after a takeover (ADR 0059).
     * The broadcaster knows an account by the connections that joined a presence channel. Nothing happens when the
     * broadcaster is not Reverb, or when it does not answer: the call never throws.
     *
     * @param  string  $userCuid  The identifier of the account for the broadcaster (User::getAuthIdentifierForBroadcasting())
     */
    public function endFor(string $userCuid): void;
}
