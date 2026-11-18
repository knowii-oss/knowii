<?php

namespace App\Contracts\Events;

use App\Models\Event;
use App\Models\User;

interface UpdatesEvents
{
    /**
     * Change the fields of an event (#1513, ADR 0053): the owner, the admins and the host. The slug stays. The state has
     * its own action (SetsEventStates). An update tells nobody.
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, Event $event, array $input): Event;
}
