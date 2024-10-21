<?php

namespace App\Events\CommunityResourceCollections;

class CommunityResourceCollectionCreated extends CommunityResourceCollectionEvent
{
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
