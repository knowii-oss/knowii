<?php

namespace App\Models;

use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place in a community where members do one kind of thing. Its type decides what it holds (#1173, ADR 0012).
 *
 * @property int $id
 * @property string $cuid
 * @property int $community_id
 * @property int $space_group_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $emoji
 * @property KnowiiSpaceType $type
 * @property KnowiiSpaceVisibility $visibility
 * @property KnowiiSpacePostingPolicy $posting_policy
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read SpaceGroup $spaceGroup
 * @property-read Collection<int, Post> $posts
 *
 * @method static \Database\Factories\SpaceFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class Space extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<SpaceFactory> */
    use HasFactory;

    use Sluggable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'name',
        'slug',
        'description',
        'emoji',
        'type',
        'visibility',
        'posting_policy',
        'position',
        'community_id',
        'space_group_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'community_id',
        'space_group_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'type' => KnowiiSpaceType::class,
            'visibility' => KnowiiSpaceVisibility::class,
            'posting_policy' => KnowiiSpacePostingPolicy::class,
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
     * @return BelongsTo<SpaceGroup, covariant $this>
     */
    final public function spaceGroup(): BelongsTo
    {
        return $this->belongsTo(SpaceGroup::class);
    }

    /**
     * @return HasMany<Post, covariant $this>
     */
    final public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * The slug is derived from the name, and unique within the community.
     *
     * @return array<string,array<string,string>>
     */
    final public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    /**
     * Slugs only need to be unique within the community of the space (cviebrock/eloquent-sluggable hook).
     *
     * @param  Builder<Space>  $query
     * @param  array<mixed>  $config
     * @return Builder<Space>
     */
    final public function scopeWithUniqueSlugConstraints(Builder $query, Model $model, string $attribute, array $config, string $slug): Builder
    {
        /** @var Space $model */
        return $query->where('community_id', $model->community_id);
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
