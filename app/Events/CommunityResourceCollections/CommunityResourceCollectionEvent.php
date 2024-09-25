<?php

namespace App\Events\CommunityResourceCollections;

use App\Models\CommunityResourceCollection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class CommunityResourceCollectionEvent
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  /**
   * The community instance.
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
}
