<?php

namespace App\Models;

use App\Enums\KnowiiInviteLinkStatus;
use App\Http\Resources\InviteLinkResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Database\Factories\InviteLinkFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An invite link (#1391, ADR 0062): an address that the managers of a community share (on a sales page, in a newsletter,
 * in a course). Any person who opens it can join the community as a member, and gets the access groups and the member
 * tags of the link. It always gives the role `member`.
 *
 * The token is 40 letters and digits. The table keeps its sha256 (`token_hash`) to find the link, and the token itself
 * encrypted with the key of the application, so that the managers copy the link again: a copy of the database without
 * that key gives no working link. No query reads a link by its token: findByToken() hashes it first.
 *
 * The name is for the managers only. The link holds no personal data of a member: the joins are rows of
 * `invite_link_uses` (InviteLinkUse).
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property int $community_id
 * @property string $name
 * @property string $token_hash
 * @property string $token
 * @property bool $active
 * @property Carbon|null $expires_at
 * @property int|null $max_uses
 * @property int $uses_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Community $community
 * @property-read Collection<int, AccessGroup> $accessGroups
 * @property-read Collection<int, MemberTag> $memberTags
 * @property-read Collection<int, InviteLinkUse> $uses
 *
 * @method static \Database\Factories\InviteLinkFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteLink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteLink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InviteLink query()
 *
 * @mixin \Eloquent
 */
#[UseResource(InviteLinkResource::class)]
class InviteLink extends Model
{
    use GeneratesCuid;

    /** @use HasFactory<InviteLinkFactory> */
    use HasFactory;

    /**
     * The characters of a token: letters and digits only (Str::random)
     */
    public const string TOKEN_PATTERN = '[A-Za-z0-9]{'.self::TOKEN_LENGTH.'}';

    public const int TOKEN_LENGTH = 40;

    /**
     * @var list<string>
     */
    protected $fillable = ['cuid', 'community_id', 'name', 'token_hash', 'token', 'active', 'expires_at', 'max_uses'];

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'community_id', 'token_hash', 'token'];

    /**
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'token' => 'encrypted',
            'active' => 'boolean',
            'expires_at' => 'datetime',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Community, covariant $this>
     */
    final public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * The access groups that a person gets at the join, by hand. A deleted group leaves the link.
     *
     * @return BelongsToMany<AccessGroup, covariant $this>
     */
    final public function accessGroups(): BelongsToMany
    {
        return $this->belongsToMany(AccessGroup::class, 'invite_link_access_groups');
    }

    /**
     * The member tags that a person gets at the join (the contract of ADR 0060). A deleted tag leaves the link.
     *
     * @return BelongsToMany<MemberTag, covariant $this>
     */
    final public function memberTags(): BelongsToMany
    {
        return $this->belongsToMany(MemberTag::class, 'invite_link_member_tags');
    }

    /**
     * Who joined through the link
     *
     * @return HasMany<InviteLinkUse, covariant $this>
     */
    final public function uses(): HasMany
    {
        return $this->hasMany(InviteLinkUse::class);
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }

    /**
     * The one place of the state, in this order: off, ended, full, else active. Only `active` lets a person join.
     */
    final public function status(): KnowiiInviteLinkStatus
    {
        if (! $this->active) {
            return KnowiiInviteLinkStatus::Off;
        }
        if ($this->expires_at !== null && ! $this->expires_at->isFuture()) {
            return KnowiiInviteLinkStatus::Ended;
        }
        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return KnowiiInviteLinkStatus::Full;
        }

        return KnowiiInviteLinkStatus::Active;
    }

    /**
     * The absolute address that the managers share
     */
    final public function url(): string
    {
        return route('invite-links.open', ['inviteLinkToken' => $this->token]);
    }

    /**
     * A new token: 40 letters and digits (about 238 bits)
     */
    public static function newToken(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The link of a token, or null. A text that is not a token finds nothing, and the lookup reads the hash only
     */
    public static function findByToken(string $token): ?self
    {
        if (preg_match('/\A'.self::TOKEN_PATTERN.'\z/', $token) !== 1) {
            return null;
        }

        return self::query()->where('token_hash', self::hashToken($token))->first();
    }

    /**
     * The link of the sha256 of a token, or null: the session of the page of a link keeps the hash, never the token. A
     * text that is not a hash finds nothing
     */
    public static function findByTokenHash(string $tokenHash): ?self
    {
        if (preg_match('/\A[0-9a-f]{64}\z/', $tokenHash) !== 1) {
            return null;
        }

        return self::query()->where('token_hash', $tokenHash)->first();
    }
}
