<?php

namespace App\Models;

use App\Enums\KnowiiMailDeliveryStatus;
use App\Enums\KnowiiMailKind;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of the delivery log (#1478, ADR 0054): one mail that the platform handed to the mailer. It links a report of the
 * mail provider (`provider_email_id`) to the account or the invitation that the mail was for, and it makes a notification
 * mail go out at most once (the claim of NotificationMail). No address, no subject, no text. Private: no resource.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property KnowiiMailKind $kind
 * @property ?int $user_id
 * @property ?int $community_invitation_id
 * @property ?int $notification_id
 * @property string $channel
 * @property KnowiiMailDeliveryStatus $status
 * @property ?string $provider_email_id
 * @property ?Carbon $sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?User $user
 * @property-read ?CommunityInvitation $communityInvitation
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MailDelivery newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MailDelivery newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MailDelivery query()
 *
 * @mixin \Eloquent
 */
class MailDelivery extends Model
{
    use GeneratesCuid;

    public const string CHANNEL_EMAIL = 'email';

    // The header that names the row in the mail: an opaque id, no personal data
    public const string HEADER = 'X-Knowii-Delivery';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'user_id',
        'community_invitation_id',
        'notification_id',
        'channel',
        'status',
        'provider_email_id',
        'sent_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'user_id',
        'community_invitation_id',
        'notification_id',
    ];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'kind' => KnowiiMailKind::class,
            'status' => KnowiiMailDeliveryStatus::class,
            'sent_at' => 'datetime',
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
     * @return BelongsTo<CommunityInvitation, covariant $this>
     */
    final public function communityInvitation(): BelongsTo
    {
        return $this->belongsTo(CommunityInvitation::class);
    }
}
