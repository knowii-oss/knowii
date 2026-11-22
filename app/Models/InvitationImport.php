<?php

namespace App\Models;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiInvitationImportState;
use App\Http\Resources\InvitationImportResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Database\Factories\InvitationImportFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An import of invitations from a CSV file (#1390, ADR 0061): the record of who uploaded it, when, its defaults, and one
 * row per data row of the file with its result. The file itself is never stored. The counts come from the rows
 * (InvitationImportCounts), never from counters.
 *
 * A `preview` lives Retention::invitationImportPreviewHours() hours; a sent import lives as long as an invitation:
 * Retention::invitationImportsDays() after its confirm.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $community_id
 * @property ?int $user_id
 * @property KnowiiInvitationImportState $state
 * @property KnowiiCommunityMemberRole $default_role
 * @property list<string> $default_access_groups
 * @property list<string> $default_member_tags
 * @property list<string> $ignored_columns
 * @property ?Carbon $confirmed_at
 * @property ?Carbon $finished_at
 * @property ?Carbon $resent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read ?User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvitationImport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvitationImport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvitationImport query()
 *
 * @mixin \Eloquent
 */
#[UseResource(InvitationImportResource::class)]
class InvitationImport extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<InvitationImportFactory> */
    use HasFactory;

    /**
     * The counts of the rows, set by InvitationImportCounts for the resource: not a column
     *
     * @var array<string, int>|null
     */
    public ?array $rowCounts = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cuid',
        'community_id',
        'user_id',
        'state',
        'default_role',
        'default_access_groups',
        'default_member_tags',
        'ignored_columns',
        'confirmed_at',
        'finished_at',
        'resent_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'community_id', 'user_id'];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'state' => KnowiiInvitationImportState::class,
            'default_role' => KnowiiCommunityMemberRole::class,
            'default_access_groups' => 'array',
            'default_member_tags' => 'array',
            'ignored_columns' => 'array',
            'confirmed_at' => 'datetime',
            'finished_at' => 'datetime',
            'resent_at' => 'datetime',
        ];
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }

    /**
     * @return BelongsTo<Community, covariant $this>
     */
    final public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * The person who uploaded the file, null for a deleted account
     *
     * @return BelongsTo<User, covariant $this>
     */
    final public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<InvitationImportRow, covariant $this>
     */
    final public function rows(): HasMany
    {
        return $this->hasMany(InvitationImportRow::class);
    }
}
