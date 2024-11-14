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

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $retVal = [
            'cuid' => $this->community->cuid,
        ];

        return $retVal;
    }
}
