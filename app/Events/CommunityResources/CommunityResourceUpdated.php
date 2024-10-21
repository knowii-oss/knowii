<?php

namespace App\Events\CommunityResources;

use Illuminate\Queue\SerializesModels;

class CommunityResourceUpdated extends CommunityResourceEvent
{
  use SerializesModels;

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
