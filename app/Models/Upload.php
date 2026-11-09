<?php

namespace App\Models;

use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An image uploaded by a member (#1178).
 *
 * @property int $id
 * @property string $cuid
 * @property int $community_id
 * @property int|null $user_id
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $width
 * @property int $height
 * @property int $size_bytes
 * @property string $alt
 * @property Carbon $created_at
 * @property-read Community $community
 *
 * @mixin \Eloquent
 */
class Upload extends Model
{
    use GeneratesCuid;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['cuid', 'community_id', 'user_id', 'disk', 'path', 'mime_type', 'width', 'height', 'size_bytes', 'alt'];

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
     * Public URL of the file.
     */
    final public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
