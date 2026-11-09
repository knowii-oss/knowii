<?php

namespace App\Models;

use App\Enums\KnowiiReactionKind;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reaction of a member to a post or a comment (#1176). Internal: the API exposes summaries, not reactions.
 *
 * @property int $id
 * @property int $community_id
 * @property int $user_id
 * @property int|null $post_id
 * @property int|null $comment_id
 * @property KnowiiReactionKind $kind
 * @property Carbon $created_at
 * @property-read User $user
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
