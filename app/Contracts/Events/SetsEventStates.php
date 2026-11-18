<?php

namespace App\Contracts\Events;

use App\Models\Event;
use App\Models\User;

interface SetsEventStates
{
    /**
     * Publish an event, put it back to draft, cancel it or publish it again (#1513, ADR 0053). The same state again
     * changes nothing.
     *
     * @param  array<mixed>  $input
     */
    public function setState(User $user, Event $event, array $input): Event;
}
