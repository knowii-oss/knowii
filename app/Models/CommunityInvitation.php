<?php

namespace App\Models;


use App\Enums\KnowiiCommunityMemberRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\CommunityInvitation as KnowiiCommunityInvitation;
use App\Models\Community;
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
      'community_id',
    ];

    /**
     * Get the community that the invitation belongs to.
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
      return [
        'role' => KnowiiCommunityMemberRole::class,
      ];
    }
}
