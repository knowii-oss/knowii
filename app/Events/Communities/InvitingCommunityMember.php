<?php

namespace App\Events\Communities;

use App\Models\Community;
use Illuminate\Foundation\Events\Dispatchable;

class InvitingCommunityMember
{
  use Dispatchable;

  /**
   * The instance.
   *
   * @var Community
   */
  public Community $community;

  /**
   * The email address of the invitee.
   *
   * @var mixed
   */
  public mixed $email;

  /**
   * The role of the invitee.
   *
   * @var mixed
   */
  public mixed $role;

  /**
   * Create a new event instance.
   *
   * @param Community $community
   * @param mixed $email
   * @param mixed $role
   * @return void
   */
  public function __construct(Community $community, mixed $email, mixed $role)
  {
    $this->community = $community;
    $this->email = $email;
    $this->role = $role;
  }
}
