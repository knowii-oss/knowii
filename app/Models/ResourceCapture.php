<?php

namespace App\Models;

use App\Enums\KnowiiCaptureSource;
use App\Http\Resources\ResourceCaptureResource;
use App\Support\CommunityResourceFiles;
use Carbon\Carbon;
use Database\Factories\ResourceCaptureFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The captured content of one community resource (ADR 0018): Markdown only, for the members of its community.
 *
 * @property-read int $id
 * @property int $community_resource_id
 * @property string $markdown
 * @property int $word_count
 * @property int $reading_time
 * @property KnowiiCaptureSource $source
 * @property Carbon $captured_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CommunityResource $communityResource
 *
 * @method static \Database\Factories\ResourceCaptureFactory factory($count = null, $state = [])
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\ResourceCaptureResource>(class-string<TResource>|null $resourceClass = null)
 */
#[UseResource(ResourceCaptureResource::class)]
class ResourceCapture extends Model
{
    /** @use HasFactory<ResourceCaptureFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_resource_id',
        'markdown',
        'word_count',
        'reading_time',
        'source',
        'captured_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'community_resource_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'source' => KnowiiCaptureSource::class,
            'captured_at' => 'datetime',
            'word_count' => 'integer',
            'reading_time' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CommunityResource, covariant $this>
     */
    final public function communityResource(): BelongsTo
    {
        return $this->belongsTo(CommunityResource::class);
    }

    /**
     * A deleted capture takes the stored images of its article with it (#1247), once the transaction commits
     */
    protected static function booted(): void
    {
        static::deleted(static function (ResourceCapture $capture) {
            $cuid = CommunityResource::query()->whereKey($capture->community_resource_id)->value('cuid');
            if (is_string($cuid)) {
                DB::afterCommit(static fn () => Storage::disk(CommunityResourceFiles::disk())->deleteDirectory(CommunityResourceFiles::folder($cuid).'/images'));
            }
        });
    }

    /**
     * The word count and the reading time (200 words per minute, at least one minute) of a Markdown text
     *
     * @return array{word_count: int, reading_time: int}
     */
    public static function measure(string $markdown): array
    {
        $words = str_word_count(strip_tags($markdown));

        return ['word_count' => $words, 'reading_time' => max(1, (int) ceil($words / 200))];
    }
}
