<?php

namespace App\Models;

use App\Enums\KnowiiEmailSuppressionReason;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The suppression of the notification email of an account (#1478, ADR 0054), about one address. Read it only through
 * App\Support\EmailSuppression: a row applies only while its address is the address of the account. Private: the member
 * reads the state through the email status of the API.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $user_id
 * @property string $email
 * @property ?KnowiiEmailSuppressionReason $reason
 * @property ?Carbon $suppressed_at
 * @property int $soft_bounce_count
 * @property ?Carbon $last_event_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSuppression newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSuppression newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSuppression query()
 *
 * @mixin \Eloquent
 */
class EmailSuppression extends Model
{
    use GeneratesCuid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'email',
        'reason',
        'suppressed_at',
        'soft_bounce_count',
        'last_event_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'user_id',
    ];

    /**
     * @var array<string, int>
     */
    protected $attributes = [
        'soft_bounce_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'reason' => KnowiiEmailSuppressionReason::class,
            'suppressed_at' => 'datetime',
            'soft_bounce_count' => 'integer',
            'last_event_at' => 'datetime',
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
