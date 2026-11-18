<?php

namespace App\Contracts\Events;

use App\Models\Event;
use App\Models\User;

interface DuplicatesEvents
{
    /**
     * A draft copy of an event, 7 days later at the same wall-clock time in the zone of the event (#1513, ADR 0053)
     */
    public function duplicate(User $user, Event $event): Event;
}
