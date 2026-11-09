<?php

namespace App\Models;

use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A post in a discussion space (#1174).
 *
 * @property int $id
 * @property string $cuid
 * @property int $community_id
 * @property int $space_id
 * @property int|null $author_id
 * @property string $title
 * @property string $slug
 * @property string $body
 * @property string $body_html
 * @property bool $is_pinned
 * @property Carbon|null $pinned_at
 * @property Carbon|null $edited_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read Space $space
 * @property-read User|null $author
 * @property-read Collection<int, Comment> $comments
 * @property-read int|null $comments_count
 *
 * @method static \Database\Factories\PostFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class Post extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use Sluggable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'community_id',
        'space_id',
        'author_id',
        'title',
        'slug',
        'body',
        'body_html',
        'is_pinned',
        'pinned_at',
        'edited_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'community_id',
        'space_id',
        'author_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'pinned_at' => 'datetime',
            'edited_at' => 'datetime',
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
     * @return BelongsTo<Space, covariant $this>
     */
    final public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * All the comments (top-level and replies), oldest first.
     *
     * @return HasMany<Comment, covariant $this>
     */
    final public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Pinned posts first, then the newest.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    final public function scopeForSpacePage(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('pinned_at')->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return array<string,array<string,string>>
     */
    final public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }

    /**
     * Slugs only need to be unique within the space.
     *
     * @param  Builder<Post>  $query
     * @param  array<mixed>  $config
     * @return Builder<Post>
     */
    final public function scopeWithUniqueSlugConstraints(Builder $query, Model $model, string $attribute, array $config, string $slug): Builder
    {
        /** @var Post $model */
        return $query->where('space_id', $model->space_id);
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
