<?php

namespace App\Models;

use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiResourceType;
use App\Events\CommunityResources\CommunityResourceCreated;
use App\Events\CommunityResources\CommunityResourceDeleted;
use App\Events\CommunityResources\CommunityResourceUpdated;
use App\Http\Resources\CommunityResourceResource;
use App\Support\CommunityResourceFiles;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Database\Factories\CommunityResourceFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

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
 * @property KnowiiResourceType $type
 * @property bool $type_chosen
 * @property KnowiiResourceLevel $level
 * @property KnowiiCaptureStatus $capture_status
 * @property ?string $capture_failure_reason
 * @property ?int $curator_id
 * @property bool $is_featured
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int $space_id
 * @property-read Community $community
 * @property-read Resource $resource
 * @property-read Space $space
 * @property-read ?UserProfile $curator
 * @property-read ?ResourceCapture $capture
 * @property-read Collection<int, Comment> $comments
 * @property-read ?int $comments_count
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
        'type',
        'type_chosen',
        'level',
        'capture_status',
        'capture_failure_reason',
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
            'type' => KnowiiResourceType::class,
            'type_chosen' => 'boolean',
            'level' => KnowiiResourceLevel::class,
            'capture_status' => KnowiiCaptureStatus::class,
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
     * The captured content (ADR 0018): for the members of this community only, never shared with another community
     *
     * @return HasOne<ResourceCapture, covariant $this>
     */
    final public function capture(): HasOne
    {
        return $this->hasOne(ResourceCapture::class);
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
     * Comments on the resource (#1034), oldest first.
     *
     * @return HasMany<Comment, covariant $this>
     */
    final public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('created_at')->orderBy('id');
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

    /**
     * A deleted community resource takes its files with it (ADR 0018), once the transaction commits
     */
    protected static function booted(): void
    {
        static::deleted(static function (CommunityResource $communityResource) {
            $cuid = $communityResource->cuid;
            DB::afterCommit(static fn () => CommunityResourceFiles::delete([$cuid]));
        });
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
