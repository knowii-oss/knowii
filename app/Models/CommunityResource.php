<?php

namespace App\Models;

use App\Events\CommunityResources\CommunityResourceCreated;
use App\Events\CommunityResources\CommunityResourceDeleted;
use App\Events\CommunityResources\CommunityResourceUpdated;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Parables\Cuid\GeneratesCuid;

/**
 * App\Models\CommunityResourceCollection
 *
 * @property int $id
 * @property string $cuid
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $resource_id
 * @property int $community_id
 * @property int|null $resource_text_article_id
 * @property int|null $curator_id
 * @property bool $is_featured
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property \App\Models\Community $community
 * @property \App\Models\Resource $resource
 * @property \App\Models\CommunityResourceCollection $collection
 * @property \App\Models\UserProfile $curator
 * @property int $collection_id
 * @property-read \App\Models\ResourceTextArticle|null $textArticle
 *
 * @method static \Database\Factories\CommunityResourceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereCollectionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereCommunityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereCuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereCuratorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereIsFeatured($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereResourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereResourceTextArticleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class CommunityResource extends Model
{
    // Automatically generate cuid2 for the model
    // Reference: https://github.com/Parables/laravel-cuid2
    use GeneratesCuid;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'slug',
        'name',
        'description',
        'resource_id',
        'community_id',
        'collection_id',
        'resource_text_article_id',
        'curator_id',
        'is_featured',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'resource_id',
        'community_id',
        'collection_id',
        'resource_text_article_id',
        'curator_id',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => CommunityResourceCreated::class,
        'updated' => CommunityResourceUpdated::class,
        'deleted' => CommunityResourceDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }

    /**
     *  Get the community that this belongs to
     *
     * @return BelongsTo<Community, covariant $this>
     */
    final public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the resource that this corresponds to
     *
     * @return BelongsTo<Resource, covariant $this>
     */
    final public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     *  Get the collection that this corresponds to
     *
     * @return BelongsTo<CommunityResourceCollection, covariant $this>
     */
    final public function collection(): BelongsTo
    {
        return $this->belongsTo(CommunityResourceCollection::class);
    }

    /**
     *  Get the text article that this corresponds to (if any)
     *
     * @return BelongsTo<ResourceTextArticle, covariant $this>
     */
    final public function textArticle(): BelongsTo
    {
        return $this->belongsTo(ResourceTextArticle::class, 'resource_text_article_id');
    }

    // WARNING: do not delete. This is used via load(...) (e.g., TextResourceApiController and ResourceResource

    /**
     * Get the resource curator
     *
     * @return BelongsTo<UserProfile, covariant $this>
     */
    final public function curator(): BelongsTo
    {
        return $this->belongsTo(UserProfile::class, 'curator_id');
    }

    /**
     * Purge
     */
    final public function purge(): void
    {
        $this->delete();
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
