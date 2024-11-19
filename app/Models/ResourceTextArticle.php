<?php

namespace App\Models;

use App\Events\Resources\TextArticles\ResourceTextArticleCreated;
use App\Events\Resources\TextArticles\ResourceTextArticleDeleted;
use App\Events\Resources\TextArticles\ResourceTextArticleUpdated;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Parables\Cuid\GeneratesCuid;

/**
 * @property int $id
 * @property string $cuid
 * @property int $resource_id
 * @property string|null $markdown
 * @property string|null $html
 * @property int|null $word_count
 * @property int|null $reading_time
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property \App\Models\Resource $resource
 * @property-read Collection<int, CommunityResource> $communityResources
 * @property-read int|null $community_resources_count
 *
 * @method static \Database\Factories\ResourceTextArticleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceTextArticle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceTextArticle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceTextArticle query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceTextArticle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceTextArticle whereCuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceTextArticle whereHtml($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceTextArticle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceTextArticle whereMarkdown($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceTextArticle whereReadingTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceTextArticle whereResourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceTextArticle whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceTextArticle whereWordCount($value)
 *
 * @mixin \Eloquent
 */
class ResourceTextArticle extends Model
{
    // Automatically generate cuid2 for the model
    // Reference: https://github.com/Parables/laravel-cuid2
    use GeneratesCuid;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'resource_id',
        'html',
        'markdown',
        'word_count',
        'reading_time',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'html',
        'resource_id',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => ResourceTextArticleCreated::class,
        'updated' => ResourceTextArticleUpdated::class,
        'deleted' => ResourceTextArticleDeleted::class,
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
     * Get the global resource that this text article corresponds to.
     *
     * @return BelongsTo<Resource, covariant $this>
     */
    final public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    /**
     * Get all of the community resources that reference this.
     *
     * @return HasMany<CommunityResource, covariant $this>
     */
    final public function communityResources(): HasMany
    {
        return $this->hasMany(CommunityResource::class);
    }

    /**
     * Purge
     */
    final public function purge(): void
    {
        $this->delete();
    }

    /**
     * Find a ResourceTextArticle by resource_id and update it, or create a new one if not found.
     */
    public static function findByResourceIdAndUpdateOrCreateNew(string $resourceId, array $attributes): ResourceTextArticle
    {
        return static::updateOrCreate(
            ['resource_id' => $resourceId],
            $attributes
        );
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
