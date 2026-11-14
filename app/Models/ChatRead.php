<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The last first-level message that a member read in a chat space (#1185): the unread markers.
 *
 * @property-read int $id
 * @property int $user_id
 * @property int $space_id
 * @property int $last_read_message_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Space $space
 */
class ChatRead extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'space_id', 'last_read_message_id'];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return ['last_read_message_id' => 'integer'];
    }

    /**
     * @return BelongsTo<Space, covariant $this>
     */
    final public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
