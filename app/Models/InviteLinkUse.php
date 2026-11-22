<?php

namespace App\Models;

use App\Http\Resources\InviteLinkUseResource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One join through an invite link (#1391, ADR 0062): the link, the member and the date. Only the join writes it
 * (JoinCommunityThroughInviteLink). It goes when the member leaves the community or is removed (Community::removeUser),
 * with the link, with the account, and after `retention.invite_link_uses_days`. No cuid and no updated_at: the row is
 * never public on its own, and it never changes.
 *
 * @property-read int $id
 * @property int $invite_link_id
 * @property int $user_id
 * @property Carbon $created_at
 * @property-read InviteLink $inviteLink
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteLinkUse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteLinkUse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteLinkUse query()
 *
 * @mixin \Eloquent
 */
#[UseResource(InviteLinkUseResource::class)]
class InviteLinkUse extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['invite_link_id', 'user_id'];

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'invite_link_id', 'user_id'];

    /**
     * @return BelongsTo<InviteLink, covariant $this>
     */
    final public function inviteLink(): BelongsTo
    {
        return $this->belongsTo(InviteLink::class);
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
