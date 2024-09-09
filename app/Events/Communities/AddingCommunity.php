<?php

namespace App\Events\Communities;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class AddingCommunity
{
  use Dispatchable;

  /**
   * The owner.
   *
   * @var User
   */
  public User $owner;

  /**
   * Create a new event instance.
   *
   * @param User $owner
   * @return void
   */
  public function __construct(User $owner)
  {
    $this->owner = $owner;
  }
}
