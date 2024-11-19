<?php

namespace App\Events\Communities;

use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Http\Resources\CommunityResource;
use App\Models\Community;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

abstract class CommunityEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /**
     * The community instance.
     */
    public Community $community;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Community $community)
    {
        $this->community = $community;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return (new CommunityResource($this->community))->toArray(request());
    }

    /**
     * @return array<Channel|PrivateChannel>
     */
    final public function broadcastOn(): array
    {
        // Emit events to the community channel
        $retVal = [
            new PrivateChannel(Str::of(Constants::$WS_CHANNEL_COMMUNITY)->replace(Constants::$WS_CHANNEL_COMMUNITY_PARAM_COMMUNITY_CUID, $this->community->cuid)),
        ];

        // Emit events about public communities to the public channel
        if ($this->community->visibility === KnowiiCommunityVisibility::Public) {
            $retVal[] = new PrivateChannel(Constants::$WS_CHANNEL_COMMUNITIES);
        }

        return $retVal;
    }
}
