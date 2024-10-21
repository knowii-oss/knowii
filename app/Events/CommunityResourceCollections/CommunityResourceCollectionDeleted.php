<?php

namespace App\Events\CommunityResourceCollections;

class CommunityResourceCollectionDeleted extends CommunityResourceCollectionEvent
{
  /**
   * The event's broadcast name.
   *
   * @return string
   */
  final public function broadcastAs(): string
  {
    return 'community.resource_collection.deleted';
  }
}
