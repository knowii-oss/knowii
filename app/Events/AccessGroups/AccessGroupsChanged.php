<?php

namespace App\Events\AccessGroups;

use App\Constants;
use App\Models\Community;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * The access groups of a community changed: a group created, renamed or deleted, a member added or removed (#1241).
 *
 * The payload is small on purpose: the open settings pages load the groups again.
 */
class AccessGroupsChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return 'community.access_groups.changed';
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
