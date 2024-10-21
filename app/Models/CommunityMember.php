<?php

namespace App\Models;

use App\Enums\KnowiiCommunityMemberRole;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Parables\Cuid\GeneratesCuid;


class CommunityMember extends Pivot
{
  // Automatically generate cuid2 for the model
  // Reference: https://github.com/Parables/laravel-cuid2
  use GeneratesCuid;

  /**
   * The table associated with the pivot model.
   *
   * @var string
   */
  protected $table = 'community_members';

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
    'community_id',
    'user_id',
  ];

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  final public function casts(): array
  {
    return [
      'role' => KnowiiCommunityMemberRole::class,
    ];
  }
}
