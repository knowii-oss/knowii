<?php

namespace App;

use App\Models\Community;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $cuid
 * @property int $community_id
 * @property string $email
 * @property string $role
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Community $community
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereCommunityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereCuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
