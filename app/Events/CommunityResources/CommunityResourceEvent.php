<?php

namespace App\Events\CommunityResources;

use App\Models\CommunityResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class CommunityResourceEvent
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

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
}
