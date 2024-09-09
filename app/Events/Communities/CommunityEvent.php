<?php

namespace App\Events\Communities;

use App\Models\Community;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class CommunityEvent
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  /**
   * The community instance.
   *
   * @var Community
   */
  public Community $community;

  /**
   * Create a new event instance.
   *
   * @param Community $community
   * @return void
   */
  public function __construct(Community $community)
  {
    $this->community = $community;
  }
}
