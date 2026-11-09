<?php

namespace App\Models;

use App\Http\Resources\AccessGroupResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named group of members of a community, for example "Paid members" (#1241, ADR 0025). The spaces with the "groups"
 * visibility open to the members of their groups.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $community_id
 * @property string $name
 * @property ?string $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read int|null $members_count
 * @property-read int|null $spaces_count
 */
#[UseResource(AccessGroupResource::class)]
class AccessGroup extends Model
{
    use GeneratesCuid;

    /**
     * @var list<string>
     */
    protected $fillable = ['cuid', 'community_id', 'name', 'description'];

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'community_id'];

    /**
     * @return BelongsTo<Community, covariant $this>
     */
    final public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return BelongsToMany<User, covariant $this>
     */
    final public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'access_group_members')->withPivot('created_at');
    }

    /**
     * @return BelongsToMany<Space, covariant $this>
     */
    final public function spaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class, 'access_group_space');
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
