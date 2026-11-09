<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member mentioned in a post or a comment (#1177).
 *
 * @property int $id
 * @property int $community_id
 * @property int $user_id
 * @property int|null $post_id
 * @property int|null $comment_id
 * @property Carbon $created_at
 * @property-read User $user
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
}
