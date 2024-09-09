<?php

namespace App\Models;

use App\Knowii;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\CommunityInvitation as KnowiiCommunityInvitation;

class CommunityInvitation extends KnowiiCommunityInvitation
{
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
     * Get the community that the invitation belongs to.
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Knowii::communityModel());
    }
}
