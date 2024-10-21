<?php

namespace App\Events\Communities;

use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Http\Resources\CommunityResource;
use App\Models\Community;
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
   *
   * @var Community
   */
  public Community $community;

  /**
   * Create a new event instance.
   *
   * @param Community $community
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

  final public function broadcastOn(): array
  {
    // Emit events to the community channel
    $retVal = [
      new PrivateChannel(Str::of(Constants::$WS_CHANNEL_COMMUNITY)->replace(Constants::$WS_CHANNEL_COMMUNITIES_COMMUNITY_PARAM_COMMUNITY_CUID, $this->community->cuid)),
    ];

    // Emit events about public communities to the public channel
    if (KnowiiCommunityVisibility::Public === $this->community->visibility) {
      $retVal[] = new PrivateChannel(Constants::$WS_CHANNEL_COMMUNITIES);
    }

    return $retVal;
  }
}
