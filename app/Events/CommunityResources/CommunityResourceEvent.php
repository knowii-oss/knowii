<?php

namespace App\Events\CommunityResources;

use App\Constants;
use App\Models\CommunityResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * A resource of a resources space was created, changed or deleted. Identifiers only, like SpaceContentChanged (ADR 0009):
 * the page loads the resource through the API, which checks the access of its reader now.
 */
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
     * @return array{community_cuid: string, space_cuid: string, resource_cuid: string}
     */
    final public function broadcastWith(): array
    {
        return [
            'community_cuid' => $this->communityResource->community->cuid,
            'space_cuid' => $this->communityResource->space->cuid,
            'resource_cuid' => $this->communityResource->cuid,
        ];
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
