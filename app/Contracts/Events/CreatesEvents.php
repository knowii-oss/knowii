<?php

namespace App\Contracts\Events;

use App\Models\Event;
use App\Models\Space;
use App\Models\User;

interface CreatesEvents
{
    /**
     * Events spaces (#1513, ADR 0053): the posting policy of the space decides who creates an event. A draft by default;
     * a published event tells the members at "Everything" after 5 minutes, unless `notify_now` (moderators of the space).
     *
     * @param  array<mixed>  $input
     */
    public function create(User $user, Space $space, array $input): Event;
}
