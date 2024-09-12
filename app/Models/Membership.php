<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Parables\Cuid\GeneratesCuid;


class Membership extends Pivot
{
    // Automatically generate cuid2 for the model
    // Reference: https://github.com/Parables/laravel-cuid2
    use GeneratesCuid;

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

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
      'id',
    ];
}
