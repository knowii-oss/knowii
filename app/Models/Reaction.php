<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reaction of a member to a post or a comment (#1176). Internal: the API exposes summaries, not reactions.
 *
 * @property-read int $id
 * @property int $community_id
 * @property int $user_id
 * @property ?int $post_id
 * @property ?int $comment_id
 * @property ?int $chat_message_id
 * @property string $emoji One of App\Support\Emoji (#1204)
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read ?Post $post
 * @property-read ?Comment $comment
 * @property-read ?ChatMessage $chatMessage
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction whereCommentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction whereCommunityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction whereEmoji($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction wherePostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Reaction extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['community_id', 'user_id', 'emoji'];

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
