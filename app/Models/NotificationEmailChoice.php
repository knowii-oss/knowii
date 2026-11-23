<?php

namespace App\Models;

use App\Enums\KnowiiNotificationEmailGroup;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a member chose for a notification email group (#1478, ADR 0054). No row means the default of the group. Slice 4b
 * writes only `off` (STATE_OFF), through the stop link of a mail. Private: the email status of the API shows it.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $user_id
 * @property KnowiiNotificationEmailGroup $group
 * @property string $state
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationEmailChoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationEmailChoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationEmailChoice query()
 *
 * @mixin \Eloquent
 */
class NotificationEmailChoice extends Model
{
    use GeneratesCuid;

    // The group sends no email
    public const string STATE_OFF = 'off';

    // No row: the default of the group (the API shows this state)
    public const string STATE_DEFAULT = 'default';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'group',
        'state',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'group' => KnowiiNotificationEmailGroup::class,
        ];
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
