<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member mentioned in a post or a comment (#1177).
 *
 * @property-read int $id
 * @property int $community_id
 * @property int $user_id
 * @property ?int $post_id
 * @property ?int $comment_id
 * @property ?int $chat_message_id
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read ?Post $post
 * @property-read ?Comment $comment
 * @property-read ?ChatMessage $chatMessage
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mention newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mention newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mention query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mention whereCommentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mention whereCommunityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mention whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mention whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mention wherePostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mention whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Mention extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['community_id', 'user_id'];

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
}
