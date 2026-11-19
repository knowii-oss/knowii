<?php

namespace App\Models;

use App\Enums\KnowiiEventLocationVisibility;
use App\Enums\KnowiiEventState;
use App\Http\Resources\EventResource;
use App\Support\CommunityRoles;
use App\Traits\GeneratesCuid;
use App\Traits\UsesUploads;
use Carbon\Carbon;
use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated item of an events space (#1513, ADR 0053): a title, a Markdown description, a start and an end, a host, a
 * meeting address or a place, and a cover. The start and the end are UTC instants; `time_zone` is the zone in which the
 * host entered them. "Past" is derived from the end, never stored.
 *
 * Not to confuse with the broadcast events of `app/Events/`.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $community_id
 * @property int $space_id
 * @property ?int $host_id Null when the account of the host was deleted ("Deleted member")
 * @property string $title
 * @property string $slug
 * @property string $body
 * @property string $body_html
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $time_zone The IANA zone in which the host entered the time
 * @property ?string $meeting_url
 * @property ?string $place
 * @property KnowiiEventLocationVisibility $location_visibility
 * @property ?int $cover_upload_id
 * @property ?string $cover_alt Empty: a decorative image
 * @property KnowiiEventState $state
 * @property int $sequence Goes up when the time, the address, the place or the state changes
 * @property ?Carbon $published_notified_at When the "new event" lines were queued
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read Space $space
 * @property-read ?User $host
 * @property-read ?Upload $cover
 * @property-read Collection<int, UploadUsage> $uploadUsages
 * @property-read ?int $upload_usages_count
 *
 * @method static \Database\Factories\EventFactory factory($count = null, $state = [])
 * @method static Builder<static> newModelQuery()
 * @method static Builder<static> newQuery()
 * @method static Builder<static> query()
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\EventResource>(class-string<TResource>|null $resourceClass = null)
 * @method static Builder<static>|Event findSimilarSlugs(string $attribute, array<string, scalar|list<string>|null> $config, string $slug)
 * @method static Builder<static>|Event past()
 * @method static Builder<static>|Event upcoming()
 * @method static Builder<static>|Event visibleTo(?\App\Models\User $user, \App\Models\Community $community)
 * @method static Builder<static>|Event whereBody($value)
 * @method static Builder<static>|Event whereBodyHtml($value)
 * @method static Builder<static>|Event whereCommunityId($value)
 * @method static Builder<static>|Event whereCoverAlt($value)
 * @method static Builder<static>|Event whereCoverUploadId($value)
 * @method static Builder<static>|Event whereCreatedAt($value)
 * @method static Builder<static>|Event whereCuid($value)
 * @method static Builder<static>|Event whereEndsAt($value)
 * @method static Builder<static>|Event whereHostId($value)
 * @method static Builder<static>|Event whereId($value)
 * @method static Builder<static>|Event whereLocationVisibility($value)
 * @method static Builder<static>|Event whereMeetingUrl($value)
 * @method static Builder<static>|Event wherePlace($value)
 * @method static Builder<static>|Event wherePublishedNotifiedAt($value)
 * @method static Builder<static>|Event whereSequence($value)
 * @method static Builder<static>|Event whereSlug($value)
 * @method static Builder<static>|Event whereSpaceId($value)
 * @method static Builder<static>|Event whereStartsAt($value)
 * @method static Builder<static>|Event whereState($value)
 * @method static Builder<static>|Event whereTimeZone($value)
 * @method static Builder<static>|Event whereTitle($value)
 * @method static Builder<static>|Event whereUpdatedAt($value)
 * @method static Builder<static>|Event withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array<string, scalar|list<string>|null> $config, string $slug)
 *
 * @mixin \Eloquent
 */
#[UseResource(EventResource::class)]
class Event extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use Sluggable;
    use UsesUploads;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'community_id',
        'space_id',
        'host_id',
        'title',
        'slug',
        'body',
        'body_html',
        'starts_at',
        'ends_at',
        'time_zone',
        'meeting_url',
        'place',
        'location_visibility',
        'cover_upload_id',
        'cover_alt',
        'state',
        'sequence',
        'published_notified_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'community_id',
        'space_id',
        'host_id',
        'cover_upload_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'location_visibility' => KnowiiEventLocationVisibility::class,
            'state' => KnowiiEventState::class,
            'sequence' => 'integer',
            'published_notified_at' => 'datetime',
        ];
    }

    /**
     * Past: the end is before now. Never stored (a stored state would need a job to change it, and to change it back
     * when the host moves the event).
     */
    final public function isPast(): bool
    {
        return $this->ends_at->isPast();
    }

    /**
     * Started: the start is now or before now
     */
    final public function hasStarted(): bool
    {
        return ! $this->starts_at->isFuture();
    }

    final public function isDraft(): bool
    {
        return $this->state === KnowiiEventState::Draft;
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
    final public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * @return BelongsTo<Upload, covariant $this>
     */
    final public function cover(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'cover_upload_id');
    }

    /**
     * The events that a reader may see in a list: never a draft, except their own drafts as host, and every draft for
     * the owner and the admins. The reader must already be allowed to read the space (SpacePolicy::view).
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    final public function scopeVisibleTo(Builder $query, ?User $user, Community $community): Builder
    {
        if ($user !== null && CommunityRoles::isOwnerOrAdmin($user, $community)) {
            return $query;
        }

        return $query->where(static function (Builder $inner) use ($user, $community): void {
            $inner->where('state', '!=', KnowiiEventState::Draft->value);
            if ($user !== null && $user->belongsToCommunity($community)) {
                $inner->orWhere('host_id', $user->id);
            }
        });
    }

    /**
     * Upcoming: the end is now or later. The drafts first (the people who may see them must not lose them under the
     * published events, design 6.1 point 5), then the next start first. The API and the page of a space use the same
     * order (#1514).
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    final public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('ends_at', '>=', now())
            // PostgreSQL sorts true after false: descending puts the drafts first
            ->orderByRaw('(state = ?) desc', [KnowiiEventState::Draft->value])
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    /**
     * Past: the end is before now, the newest start first
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    final public function scopePast(Builder $query): Builder
    {
        return $query->where('ends_at', '<', now())->orderByDesc('starts_at')->orderByDesc('id');
    }

    /**
     * @return array<string, array<string, string>>
     */
    final public function sluggable(): array
    {
        return [
            'slug' => [
                // Made once from the title: the slug stays when the title changes, so links keep working
                'source' => 'title',
            ],
        ];
    }

    /**
     * Slugs only need to be unique within the space.
     *
     * @param  Builder<Event>  $query
     * @param  array<string, scalar|list<string>|null>  $config
     * @return Builder<Event>
     */
    final public function scopeWithUniqueSlugConstraints(Builder $query, Model $model, string $attribute, array $config, string $slug): Builder
    {
        /** @var Event $model */
        return $query->where('space_id', $model->space_id);
    }

    /**
     * Record the uploads that the event shows: the images of the description and the cover, so that `uploads:prune`
     * keeps them. syncUploadUsages() deletes every row first, so the cover comes after it.
     */
    final public function syncUploads(): void
    {
        $this->syncUploadUsages($this->body);
        if ($this->cover_upload_id !== null) {
            $this->uploadUsages()->firstOrCreate(['upload_id' => $this->cover_upload_id]);
        }
    }

    protected function itemForeignKey(): string
    {
        return 'event_id';
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
