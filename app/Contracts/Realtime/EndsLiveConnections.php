<?php

namespace App\Contracts\Realtime;

use Throwable;

interface EndsLiveConnections
{
    /**
     * Ends the live connections (WebSockets) of the account on the broadcaster of the instance, after a change of its access
     * (ADR 0059, ADR 0075). The broadcaster knows the account of every connection that holds a channel: the authorization
     * of every channel is signed with the account (App\Broadcasting\AccountBoundBroadcaster). Nothing happens when the
     * broadcaster is not Reverb. Called by the job
     * App\Jobs\EndLiveConnections only, which logs a failure and tries again.
     *
     * @param  string  $userCuid  The identifier of the account for the broadcaster (User::getAuthIdentifierForBroadcasting())
     *
     * @throws Throwable The broadcaster did not answer in time, or answered with an error
     */
    public function endFor(string $userCuid): void;
}
