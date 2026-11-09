<?php

namespace App\Models;

use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Database\Factories\SpaceGroupFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An ordered, named group of spaces in the navigation of a community (#1173, ADR 0012).
 *
 * @property int $id
 * @property string $cuid
 * @property int $community_id
 * @property string $name
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read Collection<int, Space> $spaces
 *
 * @method static \Database\Factories\SpaceGroupFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class SpaceGroup extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<SpaceGroupFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'name',
        'position',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'community_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'position' => 'integer',
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
     * The spaces of the group, in navigation order.
     *
     * @return HasMany<Space, covariant $this>
     */
    final public function spaces(): HasMany
    {
        return $this->hasMany(Space::class)->orderBy('position')->orderBy('id');
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
