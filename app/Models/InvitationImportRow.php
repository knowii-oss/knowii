<?php

namespace App\Models;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiInvitationImportOutcome;
use App\Enums\KnowiiInvitationImportRefusal;
use App\Http\Resources\InvitationImportRowResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Database\Factories\InvitationImportRowFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One data row of an import of invitations (#1390, ADR 0061): the address (in lower case, as the file wrote it), the role,
 * the names of the groups and the tags with the defaults, the result and the reason. The name of the person is never
 * stored. `community_invitation_id` points at the invitation that the row created, null once it is accepted or
 * cancelled.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $invitation_import_id
 * @property int $row_number
 * @property string $email
 * @property ?KnowiiCommunityMemberRole $role
 * @property list<string> $access_group_names
 * @property list<string> $member_tag_names
 * @property KnowiiInvitationImportOutcome $outcome
 * @property ?KnowiiInvitationImportRefusal $reason
 * @property ?string $detail
 * @property ?int $community_invitation_id
 * @property ?Carbon $queued_at
 * @property int $queued_count
 * @property ?Carbon $sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read InvitationImport $invitationImport
 * @property-read ?CommunityInvitation $communityInvitation
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvitationImportRow newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvitationImportRow newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvitationImportRow query()
 *
 * @mixin \Eloquent
 */
#[UseResource(InvitationImportRowResource::class)]
class InvitationImportRow extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<InvitationImportRowFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cuid',
        'invitation_import_id',
        'row_number',
        'email',
        'role',
        'access_group_names',
        'member_tag_names',
        'outcome',
        'reason',
        'detail',
        'community_invitation_id',
        'queued_at',
        'queued_count',
        'sent_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'invitation_import_id', 'community_invitation_id'];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'role' => KnowiiCommunityMemberRole::class,
            'access_group_names' => 'array',
            'member_tag_names' => 'array',
            'outcome' => KnowiiInvitationImportOutcome::class,
            'reason' => KnowiiInvitationImportRefusal::class,
            'queued_at' => 'datetime',
            'queued_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }

    /**
     * @return BelongsTo<InvitationImport, covariant $this>
     */
    final public function invitationImport(): BelongsTo
    {
        return $this->belongsTo(InvitationImport::class);
    }

    /**
     * @return BelongsTo<CommunityInvitation, covariant $this>
     */
    final public function communityInvitation(): BelongsTo
    {
        return $this->belongsTo(CommunityInvitation::class);
    }
}
