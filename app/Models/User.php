<?php

namespace App\Models;

use App\Actions\Users\UpdateAvatar;
use App\Http\Resources\UserResource;
use App\Traits\GeneratesCuid;
use App\Traits\HasCommunities;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use TaylorNetwork\UsernameGenerator\FindSimilarUsernames;
use TaylorNetwork\UsernameGenerator\GeneratesUsernames;

/**
 * App\Models\User
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property string $username
 * @property string $name
 * @property string $email
 * @property bool $show_online_status Whether the other members see when this member is online (#1187)
 * @property ?Carbon $email_verified_at
 * @property string $password
 * @property ?string $two_factor_secret
 * @property ?string $two_factor_recovery_codes
 * @property ?Carbon $two_factor_confirmed_at
 * @property ?string $remember_token
 * @property ?string $avatar_path
 * @property-read ?string $avatar_url
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read UserProfile $profile
 * @property-read ?CommunityMember $pivot
 * @property-read Collection<int, Community> $communities
 * @property-read ?int $communities_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read ?int $notifications_count
 * @property-read Collection<int, Community> $ownedCommunities
 * @property-read ?int $owned_communities_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read ?int $tokens_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\UserResource>(class-string<TResource>|null $resourceClass = null)
 *
 * @mixin \Eloquent
 */
#[UseResource(UserResource::class)]
class User extends Authenticatable implements MustVerifyEmail
{
    use FindSimilarUsernames;

    // Automatically generate cuid2 for the model
    use GeneratesCuid;
    use GeneratesUsernames;
    use HasApiTokens;
    use HasCommunities;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'name',
        'username',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    /**
     * The URL of the avatar goes with the user to the pages (auth.user, #109).
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * The URL of the avatar, null without one (#109, ADR 0021): the initials show instead. Never a third-party
     * placeholder service (CSP, privacy).
     *
     * @return Attribute<?string, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            // A query may select some columns only: no avatar then, instead of an error in strict mode
            $path = $this->attributes['avatar_path'] ?? null;

            return is_string($path) && $path !== '' ? Storage::disk(UpdateAvatar::disk())->url($path) : null;
        });
    }

    protected $hidden = [
        // avatar_url is shown instead
        'avatar_path',
        'communities',
        'id',
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'show_online_status' => 'boolean',
        ];
    }

    /**
     * Presence channels send this identifier to the other members (#1187, ADR 0027): the cuid, never the internal id
     * (ADR 0005).
     */
    final public function getAuthIdentifierForBroadcasting(): string
    {
        return $this->cuid;
    }

    /**
     * The members this member blocked (#1186, ADR 0028), in every community
     *
     * @return BelongsToMany<User, covariant $this>
     */
    final public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_blocks', 'blocker_id', 'blocked_id')->withPivot('created_at');
    }

    /**
     * Whether one of the two members blocked the other (#1186): then neither starts nor continues a one-to-one
     * conversation with the other, and neither adds the other to a group conversation.
     */
    final public function hasBlockWith(User $other): bool
    {
        return DB::table('user_blocks')
            ->where(fn (QueryBuilder $query) => $query->where('blocker_id', $this->id)->where('blocked_id', $other->id))
            ->orWhere(fn (QueryBuilder $query) => $query->where('blocker_id', $other->id)->where('blocked_id', $this->id))
            ->exists();
    }

    /**
     * The conversations of direct messages of this member (#1186)
     *
     * @return BelongsToMany<Conversation, covariant $this>
     */
    final public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')->withPivot('last_read_message_id')->withTimestamps();
    }

    /**
     * Whether the other members can see that this member is online (#1187, ADR 0027). A new account shows it: the
     * column defaults to true, and a model built in memory does not hold it yet.
     */
    final public function showsOnlineStatus(): bool
    {
        return filter_var($this->getAttributes()['show_online_status'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get the user's profile.
     *
     * @return HasOne<UserProfile, covariant $this>
     */
    final public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
