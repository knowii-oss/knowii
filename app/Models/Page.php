<?php

namespace App\Models;

use App\Http\Resources\PageResource;
use App\Traits\GeneratesCuid;
use App\Traits\UsesUploads;
use Carbon\Carbon;
use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Markdown page of a page space (#1179), in the order of its position.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $community_id
 * @property int $space_id
 * @property ?int $author_id
 * @property string $title
 * @property string $slug
 * @property string $body
 * @property string $body_html
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read Space $space
 * @property-read ?User $author
 *
 * @method static \Database\Factories\PageFactory factory($count = null, $state = [])
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\PageResource>(class-string<TResource>|null $resourceClass = null)
 */
#[UseResource(PageResource::class)]
class Page extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use Sluggable;
    use UsesUploads;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cuid',
        'community_id',
        'space_id',
        'author_id',
        'title',
        'slug',
        'body',
        'body_html',
        'position',
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
     * @return array<string, array<string, string>>
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
     * @param  Builder<Page>  $query
     * @param  array<mixed>  $config
     * @return Builder<Page>
     */
    final public function scopeWithUniqueSlugConstraints(Builder $query, Model $model, string $attribute, array $config, string $slug): Builder
    {
        /** @var Page $model */
        return $query->where('space_id', $model->space_id);
    }

    protected function itemForeignKey(): string
    {
        return 'page_id';
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
