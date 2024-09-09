<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;


class Membership extends Pivot
{
  /**
   * The table associated with the pivot model.
   *
   * @var string
   */
  protected $table = 'community_user';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;
}
