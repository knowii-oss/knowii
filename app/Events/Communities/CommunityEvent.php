<?php

namespace App\Events\Communities;

use App\Constants;
use App\Models\Community;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * A community was created, changed or deleted. Identifiers only (ADR 0009): an open page loads what it shows again through
 * the API or Inertia, with the rights of its reader. On the channel of the community only: its members.
 */
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
     * @return array{cuid: string}
     */
    final public function broadcastWith(): array
    {
        return ['cuid' => $this->community->cuid];
    }

    /**
     * @return array<PrivateChannel>
     */
    final public function broadcastOn(): array
    {
        return [
            new PrivateChannel(Str::of(Constants::WS_CHANNEL_COMMUNITY)->replace(Constants::WS_CHANNEL_COMMUNITY_PARAM_COMMUNITY_CUID, $this->community->cuid)->toString()),
        ];
    }
}
