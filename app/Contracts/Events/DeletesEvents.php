<?php

namespace App\Contracts\Events;

use App\Models\Event;
use App\Models\User;

interface DeletesEvents
{
    /**
     * Delete an event (#1513, ADR 0053): the owner, the admins, the host and the moderators of the space.
     */
    public function delete(User $user, Event $event): void;
}
