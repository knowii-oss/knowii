<?php

namespace App\Models;

use App\Http\Resources\UserIdentityResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Database\Factories\UserIdentityFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An account of an OpenID Connect provider that a member connected (#1385, ADR 0045). The pair issuer and subject is
 * the link: the email address and the name at the provider are never stored, and neither is any token. The subject
 * is personal data of the member: it is hidden from every serialization and only the member export shows it.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $user_id
 * @property string $issuer
 * @property string $subject
 * @property Carbon $linked_at
 * @property ?Carbon $last_used_at
 * @property ?Carbon $entitlements_checked_at When the entitlement keys of this account were last read at the provider (#1386, ADR 0047)
 * @property-read User $user
 *
 * @method static \Database\Factories\UserIdentityFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserIdentity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserIdentity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserIdentity query()
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\UserIdentityResource>(class-string<TResource>|null $resourceClass = null)
 *
 * @mixin \Eloquent
 */
#[UseResource(UserIdentityResource::class)]
class UserIdentity extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<UserIdentityFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'user_id',
        'issuer',
        'subject',
        'linked_at',
        'last_used_at',
        'entitlements_checked_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'user_id',
        'subject',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'last_used_at' => 'datetime',
            'entitlements_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
