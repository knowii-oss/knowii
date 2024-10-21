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
}
