<?php

namespace App\Models;

use App\Enums\KnowiiSpaceNotificationLevel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The level that a member chose for a space (#1475, ADR 0042, design 2.2): Everything, Only what is for me, Muted. No
 * row means the default of the space. Personal data (the choices of the member): private, no cuid, no resource.
 *
 * @property-read int $id
 * @property int $user_id
 * @property int $space_id
 * @property KnowiiSpaceNotificationLevel $level
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Space $space
 */
class NotificationSpaceLevel extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'user_id',
        'space_id',
        'level',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'user_id',
        'space_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'level' => KnowiiSpaceNotificationLevel::class,
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
     * @return BelongsTo<Space, covariant $this>
     */
    final public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
