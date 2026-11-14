<?php

namespace App\Models;

use App\Enums\KnowiiCourseMaterialKind;
use App\Http\Resources\CourseMaterialResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supplementary material of a course space (#1336, ADR 0037): a file or a link, for the whole course (no page), a
 * section or a lesson.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $community_id
 * @property int $space_id
 * @property ?int $page_id
 * @property string $title
 * @property int $position
 * @property KnowiiCourseMaterialKind $kind
 * @property ?string $url
 * @property ?string $disk
 * @property ?string $path
 * @property ?string $file_name
 * @property ?string $mime_type
 * @property ?int $size_bytes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read Space $space
 * @property-read ?Page $page
 *
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\CourseMaterialResource>(class-string<TResource>|null $resourceClass = null)
 */
#[UseResource(CourseMaterialResource::class)]
class CourseMaterial extends Model
{
    use GeneratesCuid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cuid',
        'community_id',
        'space_id',
        'page_id',
        'title',
        'position',
        'kind',
        'url',
        'disk',
        'path',
        'file_name',
        'mime_type',
        'size_bytes',
    ];

    /**
     * The place of the file never leaves the server: members download it through the API
     *
     * @var list<string>
     */
    protected $hidden = ['id', 'community_id', 'space_id', 'page_id', 'disk', 'path'];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'kind' => KnowiiCourseMaterialKind::class,
            'position' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    final public function isFile(): bool
    {
        return $this->kind === KnowiiCourseMaterialKind::File;
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
     * The section or the lesson of the material; none for a material of the whole course
     *
     * @return BelongsTo<Page, covariant $this>
     */
    final public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
