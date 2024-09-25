<?php

namespace App;

use App\Models\Community;
use Illuminate\Database\Eloquent\Model;

class CommunityInvitation extends Model
{
  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'email',
    'role',
  ];

  /**
   * Get the community that the invitation belongs to.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function community()
  {
    return $this->belongsTo(Community::class);
  }
}
