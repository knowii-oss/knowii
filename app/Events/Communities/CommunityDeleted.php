<?php

namespace App\Events\Communities;

class CommunityDeleted extends CommunityEvent
{
    /**
     * The event's broadcast name.
     */
    final public function broadcastAs(): string
    {
        return 'community.deleted';
    }
}
