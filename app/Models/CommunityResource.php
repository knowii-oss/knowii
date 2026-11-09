<?php

namespace App\Models;

use App\Events\CommunityResources\CommunityResourceCreated;
use App\Events\CommunityResources\CommunityResourceDeleted;
use App\Events\CommunityResources\CommunityResourceUpdated;
use App\Http\Resources\CommunityResourceResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Database\Factories\CommunityResourceFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A resource added to a resources space of a community (ADR 0015).
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property string $slug
 * @property string $name
 * @property ?string $description
 * @property int $resource_id
 * @property int $community_id
 * @property ?int $resource_text_article_id
 * @property ?int $curator_id
 * @property bool $is_featured
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int $space_id
 * @property-read Community $community
 * @property-read Resource $resource
 * @property-read Space $space
 * @property-read ?UserProfile $curator
 * @property-read ?ResourceTextArticle $textArticle
 *
 * @method static \Database\Factories\CommunityResourceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource newestFirst()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource query()
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereSpaceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityResource whereUpdatedAt($value)
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\CommunityResourceResource>(class-string<TResource>|null $resourceClass = null)
 *
 * @mixin \Eloquent
 */
#[UseResource(CommunityResourceResource::class)]
class CommunityResource extends Model
{
    // Automatically generate cuid2 for the model
    use GeneratesCuid;

    /** @use HasFactory<CommunityResourceFactory> */
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
        'space_id',
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
        'space_id',
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
     * Get the resources space that holds this resource
     *
     * @return BelongsTo<Space, covariant $this>
     */
    final public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
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

    /**
     * The order of the resources of a space: newest first (ADR 0015).
     *
     * @param  Builder<CommunityResource>  $query
     * @return Builder<CommunityResource>
     */
    final public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->latest()->latest('id');
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
