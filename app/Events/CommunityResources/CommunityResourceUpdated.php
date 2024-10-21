<?php

namespace App\Events\CommunityResources;

class CommunityResourceUpdated extends CommunityResourceEvent
{
  /**
   * The event's broadcast name.
   *
   * @return string
   */
  final public function broadcastAs(): string
  {
    return 'community.resource.updated';
  }
}
