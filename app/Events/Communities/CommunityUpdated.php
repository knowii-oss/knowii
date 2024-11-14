<?php

namespace App\Events\Communities;

use Illuminate\Queue\SerializesModels;

class CommunityUpdated extends CommunityEvent
{
    use SerializesModels;

    /**
     * The event's broadcast name.
     */
    final public function broadcastAs(): string
    {
        return 'community.updated';
    }
}
