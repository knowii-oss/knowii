<?php

namespace App\Models;

use App\Enums\KnowiiReactionKind;
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
 * @property KnowiiReactionKind $kind
 * @property Carbon $created_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction whereCommentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction whereCommunityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Reaction whereKind($value)
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
    protected $fillable = ['community_id', 'user_id', 'kind'];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return ['kind' => KnowiiReactionKind::class];
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
