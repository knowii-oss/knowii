<?php

namespace App\Events\CommunityResourceCollections;

class CommunityResourceCollectionDeleted extends CommunityResourceCollectionEvent
{
    /**
     * The event's broadcast name.
     */
    final public function broadcastAs(): string
    {
        return 'community.resource_collection.deleted';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $retVal = [
            'cuid' => $this->communityResourceCollection->cuid,
        ];

        return $retVal;
    }
}
