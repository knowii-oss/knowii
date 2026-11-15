<?php

namespace App\Models;

use App\Http\Resources\UploadResource;
use App\Support\UploadFiles;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * An image uploaded by a member (#1178).
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $community_id
 * @property ?int $user_id
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $width
 * @property int $height
 * @property int $size_bytes
 * @property string $alt
 * @property list<int>|null $sizes The widths of the smaller versions: empty for a small image, null when not built yet
 * @property Carbon $created_at
 * @property-read Community $community
 * @property-read ?User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload whereAlt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload whereCommunityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload whereCuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload whereHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload whereSizeBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload whereSizes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload whereWidth($value)
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\UploadResource>(class-string<TResource>|null $resourceClass = null)
 *
 * @mixin \Eloquent
 */
#[UseResource(UploadResource::class)]
class Upload extends Model
{
    use GeneratesCuid;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['cuid', 'community_id', 'user_id', 'disk', 'path', 'mime_type', 'width', 'height', 'size_bytes', 'alt', 'sizes'];

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'community_id', 'user_id', 'disk', 'path'];

    /**
     * @return BelongsTo<Community, covariant $this>
     */
    final public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The posts and comments that show this upload (#1201). Without one for a day, `uploads:prune` deletes it.
     *
     * @return HasMany<UploadUsage, covariant $this>
     */
    final public function usages(): HasMany
    {
        return $this->hasMany(UploadUsage::class);
    }

    /**
     * Public URL of the file.
     */
    final public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Storage path of the version that is `$width` pixels wide.
     */
    final public function sizePath(int $width): string
    {
        return UploadFiles::sizePath($this->path, $width);
    }

    /**
     * Public URL of the version that is `$width` pixels wide.
     */
    final public function sizeUrl(int $width): string
    {
        return Storage::disk($this->disk)->url($this->sizePath($width));
    }

    /**
     * Delete the original file and its smaller versions. The one place that does it: use it, never delete `path`.
     */
    final public function deleteFiles(): void
    {
        UploadFiles::delete($this->disk, $this->path, $this->sizes);
    }

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return ['sizes' => 'array'];
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
