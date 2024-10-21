<?php

namespace App\Events\CommunityResourceCollections;

use Illuminate\Queue\SerializesModels;

class CommunityResourceCollectionCreated extends CommunityResourceCollectionEvent
{
  use SerializesModels;

  /**
   * The event's broadcast name.
   *
   * @return string
   */
  final public function broadcastAs(): string
  {
    return 'community.resource_collection.created';
  }
}
