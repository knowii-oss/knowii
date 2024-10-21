<?php

namespace App\Events\CommunityResources;

class CommunityResourceDeleted extends CommunityResourceEvent
{
  /**
   * The event's broadcast name.
   *
   * @return string
   */
  final public function broadcastAs(): string
  {
    return 'community.resource.deleted';
  }

  /**
   * Get the data to broadcast.
   *
   * @return array<string, mixed>
   */
  public function broadcastWith(): array
  {
    $retVal = [
      "cuid" => $this->communityResource->cuid,
    ];

    return $retVal;
  }
}
