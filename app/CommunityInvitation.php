<?php

namespace App;

use App\Models\Community;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
