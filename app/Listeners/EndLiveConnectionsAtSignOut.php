<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\LiveConnections;
use Illuminate\Auth\Events\Logout;

/**
 * A sign-out ends the live connections of the account (ADR 0075): the pages of its other sessions connect again by
 * themselves, the page of this session does not. The listeners of the app are found by their `handle...` methods.
 */
class EndLiveConnectionsAtSignOut
{
    final public function handle(Logout $event): void
    {
        if ($event->user instanceof User) {
            LiveConnections::endFor($event->user);
        }
    }
}
