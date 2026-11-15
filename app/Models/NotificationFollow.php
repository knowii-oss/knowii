<?php

namespace App\Models;

use App\Enums\KnowiiFollowReason;
use App\Enums\KnowiiFollowState;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a member chose for a thread (#1475, ADR 0042): a post with its comments, or a first-level chat message with its
 * replies. One row per member and thread, exactly one of the two keys set (ADR 0014). These rows are personal data (the
 * choices of the member): private, no cuid and no API resource of their own; the API answers with the state and the reason.
 *
 * @property-read int $id
 * @property int $user_id
 * @property int $community_id
 * @property ?int $post_id
 * @property ?int $chat_message_id
 * @property KnowiiFollowState $state
 * @property KnowiiFollowReason $reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read ?Post $post
 * @property-read ?ChatMessage $chatMessage
 */
class NotificationFollow extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'user_id',
        'community_id',
        'post_id',
        'chat_message_id',
        'state',
        'reason',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'user_id',
        'community_id',
        'post_id',
        'chat_message_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'state' => KnowiiFollowState::class,
            'reason' => KnowiiFollowReason::class,
        ];
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Post, covariant $this>
     */
    final public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<ChatMessage, covariant $this>
     */
    final public function chatMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class);
    }
}
