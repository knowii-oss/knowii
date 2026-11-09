<?php

namespace App\Models;

use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Http\Resources\SpaceResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place in a community where members do one kind of thing. Its type decides what it holds (#1173, ADR 0012).
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $community_id
 * @property int $space_group_id
 * @property string $name
 * @property string $slug
 * @property ?string $description
 * @property ?string $emoji
 * @property KnowiiSpaceType $type
 * @property KnowiiSpaceVisibility $visibility
 * @property-read Collection<int, AccessGroup> $accessGroups
 * @property KnowiiSpacePostingPolicy $posting_policy
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read SpaceGroup $spaceGroup
 * @property-read Collection<int, Post> $posts
 * @property-read ?int $posts_count
 * @property-read Collection<int, CommunityResource> $resources
 * @property-read ?int $resources_count
 *
 * @method static \Database\Factories\SpaceFactory factory($count = null, $state = [])
 * @method static Builder<static>|Space findSimilarSlugs(string $attribute, array<mixed> $config, string $slug)
 * @method static Builder<static>|Space newModelQuery()
 * @method static Builder<static>|Space newQuery()
 * @method static Builder<static>|Space query()
 * @method static Builder<static>|Space whereCommunityId($value)
 * @method static Builder<static>|Space whereCreatedAt($value)
 * @method static Builder<static>|Space whereCuid($value)
 * @method static Builder<static>|Space whereDescription($value)
 * @method static Builder<static>|Space whereEmoji($value)
 * @method static Builder<static>|Space whereId($value)
 * @method static Builder<static>|Space whereName($value)
 * @method static Builder<static>|Space wherePosition($value)
 * @method static Builder<static>|Space wherePostingPolicy($value)
 * @method static Builder<static>|Space whereSlug($value)
 * @method static Builder<static>|Space whereSpaceGroupId($value)
 * @method static Builder<static>|Space whereType($value)
 * @method static Builder<static>|Space whereUpdatedAt($value)
 * @method static Builder<static>|Space whereVisibility($value)
 * @method static Builder<static>|Space withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array<mixed> $config, string $slug)
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\SpaceResource>(class-string<TResource>|null $resourceClass = null)
 *
 * @mixin \Eloquent
 */
#[UseResource(SpaceResource::class)]
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
     * The access groups that open the space when its visibility is "groups" (#1241, ADR 0025).
     *
     * @return BelongsToMany<AccessGroup, covariant $this>
     */
    final public function accessGroups(): BelongsToMany
    {
        return $this->belongsToMany(AccessGroup::class, 'access_group_space');
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
     * The pages of a page space (#1179), in their order
     *
     * @return HasMany<Page, covariant $this>
     */
    final public function pages(): HasMany
    {
        return $this->hasMany(Page::class)->orderBy('position')->orderBy('id');
    }

    /**
     * The resources of a resources space (ADR 0015). Named after the route parameter, for scoped bindings.
     *
     * @return HasMany<CommunityResource, covariant $this>
     */
    final public function resources(): HasMany
    {
        return $this->hasMany(CommunityResource::class);
    }

    /**
     * Whether the space already holds a resource that points to this URL.
     */
    final public function containsResourceWithUrl(string $url): bool
    {
        return $this->resources()
            ->whereHas('resource', fn (Builder $query) => $query->where('url', $url))
            ->exists();
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
