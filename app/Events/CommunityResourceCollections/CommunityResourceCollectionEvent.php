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
   *
   * @var CommunityResourceCollection
   */
  public CommunityResourceCollection $communityResourceCollection;

  /**
   * Create a new event instance.
   *
   * @param CommunityResourceCollection $communityResourceCollection
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
    // Emit events to the community channel
    $retVal = [
      new PrivateChannel(Str::of(Constants::$WS_CHANNEL_COMMUNITY)->replace(Constants::$WS_CHANNEL_COMMUNITIES_COMMUNITY_PARAM_COMMUNITY_CUID, $this->communityResourceCollection->community->cuid)),
    ];

    // Emit events about public communities to the public channel
    if (KnowiiCommunityVisibility::Public === $this->communityResourceCollection->community->visibility) {
      $retVal[] = new Channel(Constants::$WS_CHANNEL_COMMUNITIES);
    }

    return $retVal;
  }
}
