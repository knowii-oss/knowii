<?php

namespace App\Models;

use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiResourceType;
use App\Events\Resources\ResourceCreated;
use App\Events\Resources\ResourceDeleted;
use App\Events\Resources\ResourceUpdated;
use Carbon\Carbon;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Parables\Cuid\GeneratesCuid;

/**
 * App\Models\Resource
 *
 * @property int $id
 * @property string $cuid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $excerpt
 * @property array<string,string> $keywords // FIXME invalid type?
 * @property string|null $ai_summary
 * @property Carbon|null $published_at
 * @property Carbon|null $modified_at
 * @property string|null $language
 * @property string|null $url
 * @property string|null $cover_image_url
 * @property string|null $cover_image_alt
 * @property string|null $cover_image_base64
 * @property KnowiiResourceType $type
 * @property KnowiiResourceLevel $level
 * @property bool $is_featured
 * @property int $view_count
 * @property int $share_count
 * @property Carbon|null $last_captured_at
 * @property Carbon|null $last_checked_at
 * @property int|null $check_failures_count
 * @property bool|null $is_unavailable
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, ResourceTextArticle> $textArticles
 * @property-read int|null $text_articles_count
 *
 * @method static \Database\Factories\ResourceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource findSimilarSlugs(string $attribute, array $config, string $slug)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereAiSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereCheckFailuresCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereCoverImageAlt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereCoverImageBase64($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereCoverImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereCuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereExcerpt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereIsFeatured($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereIsUnavailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereKeywords($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereLastCapturedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereLastCheckedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereModifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereShareCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereViewCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array $config, string $slug)
 *
 * @mixin \Eloquent
 */
class Resource extends Model
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
     * @var array<int, string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'name',
        'slug',
        'excerpt',
        'keywords',
        'description',
        'ai_summary',
        'published_at',
        'modified_at',
        'language',
        'url',
        'cover_image_url',
        'cover_image_alt',
        'cover_image_base64',
        'type',
        'level',
        'is_featured',
        'view_count',
        'share_count',
        'last_captured_at',
        'last_checked_at',
        'check_failures_count',
        'is_unavailable',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => ResourceCreated::class,
        'updated' => ResourceUpdated::class,
        'deleted' => ResourceDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'keywords' => 'array', // Stored as JSONB and casted to array
            'type' => KnowiiResourceType::class,
            'level' => KnowiiResourceLevel::class,
            'published_at' => 'datetime',
            'modified_at' => 'datetime',
            'last_captured_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * Purge
     */
    final public function purge(): void
    {
        $this->delete();
    }

    /**
     * Return the sluggable configuration array for this model.
     * Reference: https://github.com/cviebrock/eloquent-sluggable
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
     * @return HasMany<ResourceTextArticle, covariant $this>
     */
    final public function textArticles(): HasMany
    {
        return $this->hasMany(ResourceTextArticle::class);
    }

    /**
     * Find a resource by URL or create a new one if it doesn't exist.
     */
    public static function findByUrlAndUpdateOrCreateNew(string $url, array $attributes = []): self
    {
        $resource = self::where('url', $url)->first();

        if (! $resource) {
            $attributes['url'] = $url;
            $resource = self::create($attributes);
        } else {
            $resource->update($attributes);
        }

        return $resource;
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
