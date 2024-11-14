<?php

namespace App\Events\CommunityResourceCollections;

use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Http\Resources\CommunityResourceCollectionResource;
use App\Models\CommunityResourceCollection;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

abstract class CommunityResourceCollectionEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /**
     * The community resource collection instance.
     */
    public CommunityResourceCollection $communityResourceCollection;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(CommunityResourceCollection $communityResourceCollection)
    {
        $this->communityResourceCollection = $communityResourceCollection;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return (new CommunityResourceCollectionResource($this->communityResourceCollection))->toArray(request());
    }

    final public function broadcastOn(): array
    {
        $retVal = [
            // Emit to the community channel
            new PrivateChannel(Str::of(Constants::$WS_CHANNEL_COMMUNITY)->replace(Constants::$WS_CHANNEL_COMMUNITY_PARAM_COMMUNITY_CUID, $this->communityResourceCollection->community->cuid)),
            // Emit to the resource collection channel
            new PrivateChannel(Str::of(Constants::$WS_CHANNEL_COMMUNITY_RESOURCE_COLLECTION)->replace(Constants::$WS_CHANNEL_COMMUNITY_RESOURCE_COLLECTION_PARAM_COMMUNITY_CUID, $this->communityResourceCollection->community->cuid)->replace(Constants::$WS_CHANNEL_COMMUNITY_RESOURCE_COLLECTION_PARAM_RESOURCE_COLLECTION_CUID, $this->communityResourceCollection->cuid)),
        ];

        // Emit events about public communities to the public channel
        if ($this->communityResourceCollection->community->visibility === KnowiiCommunityVisibility::Public) {
            $retVal[] = new Channel(Constants::$WS_CHANNEL_COMMUNITIES);
        }

        return $retVal;
    }
}
