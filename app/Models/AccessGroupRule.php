<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rule of an access group: "a member who holds this entitlement key of the sign-in provider is in this group"
 * (#1386, ADR 0047). The key is free text that the platform never interprets. A rule only works in a community that the
 * operator lists in OIDC_ENTITLEMENT_COMMUNITIES. The rules go with their group.
 *
 * @property-read int $id
 * @property int $access_group_id
 * @property string $entitlement_key
 * @property Carbon $created_at
 * @property-read AccessGroup $accessGroup
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccessGroupRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccessGroupRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccessGroupRule query()
 *
 * @mixin \Eloquent
 */
class AccessGroupRule extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'access_group_id',
        'entitlement_key',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'access_group_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<AccessGroup, covariant $this>
     */
    final public function accessGroup(): BelongsTo
    {
        return $this->belongsTo(AccessGroup::class);
    }
}
