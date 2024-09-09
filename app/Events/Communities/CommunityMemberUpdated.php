<?php

namespace App\Events\Communities;

use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class CommunityMemberUpdated
{
  use Dispatchable;

  /**
   * The instance.
   *
   * @var Community
   */
  public Community $community;

  /**
   * The member that was updated.
   *
   * @var User
   */
  public User $user;

  /**
   * Create a new event instance.
   *
   * @param Community $community
   * @param User $user
   * @return void
   */
  public function __construct(Community $community, User $user)
  {
    $this->community = $community;
    $this->user = $user;
  }
}
