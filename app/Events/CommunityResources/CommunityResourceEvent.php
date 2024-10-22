<?php

namespace App\Events\CommunityResources;

use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Http\Resources\CommunityResourceResource;
use App\Models\CommunityResource;
use Illuminate\Broadcasting\Channel;
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
   *
   * @var CommunityResource
   */
  public CommunityResource $communityResource;

  /**
   * Create a new event instance.
   *
   * @param CommunityResource $communityResource
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
      'textArticle',
      'collection',
    ]);

    return (new CommunityResourceResource($this->communityResource, false))->toArray(request());
  }

  final public function broadcastOn(): array
  {
    $retVal = [
      // Emit to the community channel
      new PrivateChannel(Str::of(Constants::$WS_CHANNEL_COMMUNITY)->replace(Constants::$WS_CHANNEL_COMMUNITY_PARAM_COMMUNITY_CUID, $this->communityResource->community->cuid)),
      // Emit to the resource collection channel
      new PrivateChannel(Str::of(Constants::$WS_CHANNEL_COMMUNITY_RESOURCE_COLLECTION)->replace(Constants::$WS_CHANNEL_COMMUNITY_RESOURCE_COLLECTION_PARAM_COMMUNITY_CUID, $this->communityResource->community->cuid)->replace(Constants::$WS_CHANNEL_COMMUNITY_RESOURCE_COLLECTION_PARAM_RESOURCE_COLLECTION_CUID, $this->communityResource->collection->cuid)),
    ];

    // Emit events about public communities to the public channel
    if (KnowiiCommunityVisibility::Public === $this->communityResource->community->visibility) {
      $retVal[] = new Channel(Constants::$WS_CHANNEL_COMMUNITIES);
    }

    return $retVal;
  }
}
