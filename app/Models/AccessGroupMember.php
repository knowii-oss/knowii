<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The membership of one person in one access group (#1241, ADR 0025). One row per member and group, with the source of
 * the membership (#1386, ADR 0047): `manual` (added by hand or by the API) and `by_rule` (an entitlement rule gives it).
 * The row exists while one of the two is true, so the reading rule of ADR 0041 and the member counts never change.
 * Never serialized.
 *
 * @property-read int $id
 * @property int $access_group_id
 * @property int $user_id
 * @property bool $manual
 * @property bool $by_rule
 * @property Carbon $created_at
 * @property-read AccessGroup $accessGroup
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccessGroupMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccessGroupMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccessGroupMember query()
 *
 * @mixin \Eloquent
 */
class AccessGroupMember extends Model
{
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'access_group_members';

    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'access_group_id',
        'user_id',
        'manual',
        'by_rule',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'access_group_id',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'manual' => 'boolean',
            'by_rule' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AccessGroup, covariant $this>
     */
    final public function accessGroup(): BelongsTo
    {
        return $this->belongsTo(AccessGroup::class);
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
