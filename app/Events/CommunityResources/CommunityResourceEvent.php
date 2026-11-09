<?php

namespace App\Events\CommunityResources;

use App\Constants;
use App\Models\CommunityResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

abstract class CommunityResourceEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /**
     * The community resource instance.
     */
    public CommunityResource $communityResource;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(CommunityResource $communityResource)
    {
        $this->communityResource = $communityResource;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {

        // Disable wrapping for the data we return to the frontend from this controller
        // This lets us use the API Resources classes without the wrapping that is normally applied
        JsonResource::withoutWrapping();

        $this->communityResource->load([
            'resource',
            'curator',
            'capture',
            'space',
        ]);

        return $this->communityResource->toResource()->toArray(request());
    }

    /**
     * The channel of the space only: it checks SpacePolicy::view, so a members-only space never leaks (ADR 0015).
     *
     * @return array<PrivateChannel>
     */
    final public function broadcastOn(): array
    {
        $space = $this->communityResource->space;

        return [
            new PrivateChannel(Str::of(Constants::WS_CHANNEL_COMMUNITY_SPACE)
                ->replace(Constants::WS_CHANNEL_COMMUNITY_SPACE_PARAM_COMMUNITY_CUID, $this->communityResource->community->cuid)
                ->replace(Constants::WS_CHANNEL_COMMUNITY_SPACE_PARAM_SPACE_CUID, $space->cuid)
                ->toString()),
        ];
    }
}
