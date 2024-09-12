<?php

namespace App\Models;

use App\Knowii;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\CommunityInvitation as KnowiiCommunityInvitation;
use Parables\Cuid\GeneratesCuid;

class CommunityInvitation extends KnowiiCommunityInvitation
{
  // Automatically generate cuid2 for the model
  // Reference: https://github.com/Parables/laravel-cuid2
  use GeneratesCuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
      'id',
    ];

    /**
     * Get the community that the invitation belongs to.
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Knowii::communityModel());
    }
}
