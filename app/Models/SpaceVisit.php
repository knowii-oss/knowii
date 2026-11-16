<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The last visit of a member to a space (#1476, ADR 0048): the read marker behind the dots of the navigation. One row per
 * member and space, replaced at each visit, no history. Personal data (where the member was): private, no cuid, no
 * resource. The rows go with the account, with the space and with the membership.
 *
 * @property-read int $id
 * @property int $user_id
 * @property int $space_id
 * @property Carbon $visited_at
 * @property-read User $user
 * @property-read Space $space
 */
class SpaceVisit extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'user_id',
        'space_id',
        'visited_at',
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
            'visited_at' => 'datetime',
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
