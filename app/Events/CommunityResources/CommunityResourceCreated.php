<?php

namespace App\Events\CommunityResources;

use Illuminate\Queue\SerializesModels;

class CommunityResourceCreated extends CommunityResourceEvent
{
    use SerializesModels;

    /**
     * The event's broadcast name.
     */
    final public function broadcastAs(): string
    {
        return 'community.resource.created';
    }
}
