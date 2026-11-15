<?php

namespace App\Models;

use App\Http\Resources\CommunityJoinBlockResource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member whom a manager removed from a community: they cannot join it again by themselves (#1486, ADR 0044). The
 * smallest thing that keeps the rule: the community, the person and the date. The row is written by the removal (never
 * when a member leaves), and an accepted invitation or a manager deletes it. It goes with the community and with the
 * account of the person (ADR 0024). Never serialized: the member export and the managers read it through their own
 * code.
 *
 * @property-read int $id
 * @property int $community_id
 * @property int $user_id
 * @property Carbon $created_at
 * @property-read Community $community
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityJoinBlock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityJoinBlock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityJoinBlock query()
 *
 * @mixin \Eloquent
 */
#[UseResource(CommunityJoinBlockResource::class)]
class CommunityJoinBlock extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'community_id',
        'user_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'community_id',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Community, covariant $this>
     */
    final public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
