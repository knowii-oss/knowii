<?php

namespace App\Models;

use App\Events\CommunityResourceCollections\CommunityResourceCollectionCreated;
use App\Events\CommunityResourceCollections\CommunityResourceCollectionDeleted;
use App\Events\CommunityResourceCollections\CommunityResourceCollectionUpdated;
use Carbon\Carbon;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Parables\Cuid\GeneratesCuid;

/**
 * App\Models\CommunityResourceCollection
 *
 * @property int $id
 * @property string $cuid
 * @property int $community_id
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Community $community
 * @property-read Collection<int, CommunityResource> $communityResources
 * @property-read int|null $community_resources_count
 *
 * @method static \Database\Factories\CommunityResourceCollectionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResourceCollection findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResourceCollection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResourceCollection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResourceCollection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResourceCollection whereCommunityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResourceCollection whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResourceCollection whereCuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResourceCollection whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResourceCollection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResourceCollection whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResourceCollection whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResourceCollection whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResourceCollection withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 *
 * @mixin \Eloquent
 */
class CommunityResourceCollection extends Model
{
    // Automatically generate cuid2 for the model
    // Reference: https://github.com/Parables/laravel-cuid2
    use GeneratesCuid;
    use HasFactory;

    // Automatically generate slugs
    // Reference: https://github.com/cviebrock/eloquent-sluggable
    use Sluggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'name',
        'slug',
        'description',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'community_id',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => CommunityResourceCollectionCreated::class,
        'updated' => CommunityResourceCollectionUpdated::class,
        'deleted' => CommunityResourceCollectionDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
        ];
    }

    /**
     * Get the community that this belongs to
     *
     * @return BelongsTo<Community, covariant $this>
     */
    final public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the community resources associated with this collection
     *
     * @return HasMany<CommunityResource, covariant $this>
     */
    final public function communityResources(): HasMany
    {
        return $this->hasMany(CommunityResource::class, 'collection_id');
    }

    /**
     * Purge the collection.
     */
    final public function purge(): void
    {
        $this->delete();
    }

    /**
     * Return the sluggable configuration array for this model.
     * Reference: https://github.com/cviebrock/eloquent-sluggable
     *
     * @return array<string,array<string,string>>
     */
    final public function sluggable(): array
    {
        // The slug is derived from the name (and uniqueness is ensured)
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    /**
     * Checks if there is already a community resource in this collection that points to a resource having the given URL
     */
    final public function containsCommunityResourcePointingToResourceWithUrl(string $url): bool
    {
        return $this->communityResources()
            ->whereHas('resource', function ($query) use ($url) {
                $query->where('url', $url);
            })
            ->exists();
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
