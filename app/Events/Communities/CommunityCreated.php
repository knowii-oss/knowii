<?php

namespace App\Events\Communities;

class CommunityCreated extends CommunityEvent
{
  /**
   * The event's broadcast name.
   *
   * @return string
   */
  final public function broadcastAs(): string
  {
    return 'community.created';
  }
}
