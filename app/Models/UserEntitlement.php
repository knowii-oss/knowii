<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One active entitlement key of a member at the sign-in provider (#1386, ADR 0047). Only the key: no grant, source or
 * date of the provider is stored. The set of a member is replaced as a whole at each read. The rows go with the account
 * (ADR 0024) and with the link to the provider. Never serialized: the member sees them through their own code.
 *
 * @property-read int $id
 * @property int $user_id
 * @property string $issuer
 * @property string $key
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEntitlement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEntitlement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEntitlement query()
 *
 * @mixin \Eloquent
 */
class UserEntitlement extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'user_id',
        'issuer',
        'key',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'user_id',
    ];

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
