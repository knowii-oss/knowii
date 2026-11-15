<?php

namespace App\Models;

use App\Enums\KnowiiNotificationKind;
use App\Http\Resources\NotificationResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A notification (#1474, ADR 0042): one row per recipient, about one item (a post, a comment or a chat message,
 * exactly one, ADR 0014). It holds no text of content: the list builds titles and excerpts from the live content.
 *
 * Seen means the line was on screen, read means it was opened or marked done (design 6.1). An open row (not read)
 * bundles the next comments on the same thing; a read row is never opened again.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $user_id
 * @property int $community_id
 * @property ?int $space_id
 * @property KnowiiNotificationKind $kind
 * @property ?int $actor_id
 * @property ?int $post_id
 * @property ?int $comment_id
 * @property ?int $chat_message_id
 * @property string $dedupe_key
 * @property ?Carbon $seen_at
 * @property ?Carbon $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Community $community
 * @property-read ?Space $space
 * @property-read ?User $actor
 * @property-read ?Post $post
 * @property-read ?Comment $comment
 * @property-read ?ChatMessage $chatMessage
 * @property-read Collection<int, User> $bundledActors The authors that a bundled row names (set by the list, not stored)
 * @property-read int $others_count The authors that a bundled row does not name (set by the list, not stored)
 *
 * @method static \Database\Factories\NotificationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification query()
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\NotificationResource>(class-string<TResource>|null $resourceClass = null)
 *
 * @mixin \Eloquent
 */
#[UseResource(NotificationResource::class)]
class Notification extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'user_id',
        'community_id',
        'space_id',
        'kind',
        'actor_id',
        'post_id',
        'comment_id',
        'chat_message_id',
        'dedupe_key',
        'seen_at',
        'read_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'user_id',
        'community_id',
        'space_id',
        'actor_id',
        'post_id',
        'comment_id',
        'chat_message_id',
        'dedupe_key',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'kind' => KnowiiNotificationKind::class,
            'seen_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * The recipient
     *
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * The member who caused the row. Null when their account is deleted.
     *
     * @return BelongsTo<User, covariant $this>
     */
    final public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Post, covariant $this>
     */
    final public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<Comment, covariant $this>
     */
    final public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /**
     * @return BelongsTo<ChatMessage, covariant $this>
     */
    final public function chatMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class);
    }

    /**
     * The rows of the levels that the bell and the list show, "Needs you" (design 2.1)
     *
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    final public function scopeNeedingYou(Builder $query): Builder
    {
        return $query->whereIn('kind', KnowiiNotificationKind::valuesOfLevel(1));
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
