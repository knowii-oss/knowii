<?php

namespace App\Models;

use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiResourceType;
use App\Events\Resources\ResourceCreated;
use App\Events\Resources\ResourceDeleted;
use App\Events\Resources\ResourceUpdated;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Parables\Cuid\GeneratesCuid;

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
