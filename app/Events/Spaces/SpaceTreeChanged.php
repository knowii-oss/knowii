<?php

namespace App\Events\Spaces;

use App\Constants;
use App\Models\Community;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * The space groups or spaces of a community changed (created, renamed, moved, reordered, deleted).
 *
 * The payload is small on purpose: the open pages reload their navigation, which only contains what each member may see.
 */
class SpaceTreeChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly string $communityCuid) {}

    public static function for(Community $community): self
    {
        return new self($community->cuid);
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'community_cuid' => $this->communityCuid,
        ];
    }

    public function broadcastAs(): string
    {
        return 'community.space_tree.changed';
    }

    /**
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(Str::of(Constants::WS_CHANNEL_COMMUNITY)->replace(Constants::WS_CHANNEL_COMMUNITY_PARAM_COMMUNITY_CUID, $this->communityCuid)->toString()),
        ];
    }
}
