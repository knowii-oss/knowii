<?php

namespace App\Models;

use App\Actions\Users\UpdateAvatar;
use App\Http\Resources\UserResource;
use App\Notifications\ResetPasswordLink;
use App\Notifications\VerifyEmailAddress;
use App\Support\Landing;
use App\Support\Retention;
use App\Traits\GeneratesCuid;
use App\Traits\HasCommunities;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property bool $show_in_member_list Whether the other members find this member in the member list of their communities (#1466)
 * @property ?string $time_zone The IANA zone that the member chose (#1476); null: the zone of the instance. Only the member reads it
 * @property ?Carbon $last_visit_on The date of the last visit, without an hour (#1476). Nobody reads it: hidden from every serialization
 * @property bool $notification_popups Whether the app shows pop-ups for the lines that need the member (#1476)
 * @property ?Carbon $email_verified_at
 * @property ?string $pending_email The new address that the member asked for, until it is confirmed or cancelled (#1706, ADR 0058). Null when none, and when it waited for more than Retention::pendingEmailDays() days. Hidden from every serialization: only the member reads it (User::ownPendingEmailChange())
 * @property ?Carbon $pending_email_requested_at The time of the request of the pending address (#1706). Hidden from every serialization
 * @property int $session_version The number that each session of the account holds from its sign-in (ADR 0059): a takeover adds 1, and a session with another number is signed out. Hidden from every serialization
 * @property ?string $password Null for an account without a password (created by an OpenID Connect provider, or its password was removed, #1385)
 * @property ?Carbon $deletion_signed_in_at The time of the new sign-in at the provider that proves the intent to delete an account without a password (#1521, ADR 0052). Null when none, or when a deletion used it. Hidden from every serialization
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
 * @property-read Collection<int, UserIdentity> $identities
 * @property-read ?int $identities_count
 * @property-read Collection<int, UserEntitlement> $entitlements
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
     * A new account holds the version 0 of the sessions (ADR 0059), as the default of the column: the model knows it from
     * its first write, so its updates check it (setKeysForSaveQuery()).
     */
    protected $attributes = [
        'session_version' => 0,
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

    /**
     * The pending address of a change of the email address (#1706, ADR 0058), or null: none, or it waited for more than
     * Retention::pendingEmailDays() days (retention:prune removes it later). Every read gets this value: the code, the own
     * resource and the account of the page. A query may select some columns only: a missing column counts as null.
     *
     * It is stored in lower case, as the address of the account (email()).
     *
     * @return Attribute<?string, ?string>
     */
    protected function pendingEmail(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $value = $this->attributes['pending_email'] ?? null;
                $requestedAt = $this->attributes['pending_email_requested_at'] ?? null;
                if (! is_string($value) || $requestedAt === null) {
                    return null;
                }

                return $this->asDateTime($requestedAt)->greaterThan(now()->subDays(Retention::pendingEmailDays())) ? $value : null;
            },
            set: static fn (?string $value): ?string => self::lowerCaseAddress($value),
        );
    }

    /**
     * The address of the account is stored in lower case, at every write of the model: the sign-up, the provider, the
     * correction and the change of the address, the factories. Fortify lowers the address of the sign-in and of the
     * password reset forms, so a person signs in and resets the password with any casing of the address
     *
     * @return Attribute<string, ?string>
     */
    protected function email(): Attribute
    {
        return Attribute::set(static fn (?string $value): ?string => self::lowerCaseAddress($value));
    }

    /**
     * An address in lower case, as the addresses of the accounts are stored; null stays null
     */
    public static function lowerCaseAddress(?string $address): ?string
    {
        return $address === null ? null : mb_strtolower($address);
    }

    /**
     * The change of the email address that waits, for the member only (#1706, ADR 0058): the own resource and the account
     * of the page add it, because both columns are hidden from every serialization. After the period both are null.
     *
     * @return array{pending_email: ?string, pending_email_requested_at: ?Carbon}
     */
    final public function ownPendingEmailChange(): array
    {
        $pending = $this->pending_email;

        return [
            'pending_email' => $pending,
            'pending_email_requested_at' => $pending !== null ? $this->pending_email_requested_at : null,
        ];
    }

    protected $hidden = [
        // avatar_url is shown instead
        'avatar_path',
        'communities',
        'id',
        'password',
        'remember_token',
        // Nobody reads it, not even the member (#1476, ADR 0048): the own export holds it
        'last_visit_on',
        // The proof of a deletion (#1521, ADR 0052): only the server reads it
        'deletion_signed_in_at',
        // A change of the email address that waits (#1706, ADR 0058): only the member reads it, through
        // ownPendingEmailChange()
        'pending_email',
        'pending_email_requested_at',
        // The version of the sessions (ADR 0059): only the server reads it
        'session_version',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * A change of the email address lifts the suppression of the notification email of the address of before at once
     * (#1478, ADR 0054): the rows of another address go, on every path that saves the account (the profile form, the
     * confirmation of a new address, a takeover, an operator). App\Support\EmailSuppression also compares the address.
     */
    protected static function booted(): void
    {
        static::updated(static function (User $user): void {
            if ($user->wasChanged('email')) {
                EmailSuppression::query()->where('user_id', $user->id)->where('email', '!=', mb_strtolower($user->email))->delete();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'pending_email_requested_at' => 'datetime',
            'session_version' => 'integer',
            'password' => 'hashed',
            'show_online_status' => 'boolean',
            'show_in_member_list' => 'boolean',
            'notification_popups' => 'boolean',
            'last_visit_on' => 'date',
            'deletion_signed_in_at' => 'datetime',
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
     * Whether the other members find this member in the member list of their communities (#1466). The team of a community
     * is always in its list, whatever this says (CommunityMemberQuery). A new account is shown: the column defaults to
     * true, and a model built in memory does not hold it yet.
     */
    final public function showsInMemberList(): bool
    {
        return filter_var($this->getAttributes()['show_in_member_list'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The time zone that the member chose (#1476), null when they did not: the zone of the instance applies
     */
    final public function timeZone(): ?string
    {
        $value = $this->getAttributes()['time_zone'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Whether the app shows pop-ups (#1476). A new account shows them: the column defaults to true, and a model built in
     * memory does not hold it yet.
     */
    final public function showsNotificationPopups(): bool
    {
        return filter_var($this->getAttributes()['notification_popups'] ?? true, FILTER_VALIDATE_BOOLEAN);
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

    /**
     * The accounts of OpenID Connect providers that this member connected (#1385, ADR 0045)
     *
     * @return HasMany<UserIdentity, covariant $this>
     */
    final public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    /**
     * The entitlement keys that the sign-in provider reported for this member (#1386, ADR 0047)
     *
     * @return HasMany<UserEntitlement, covariant $this>
     */
    final public function entitlements(): HasMany
    {
        return $this->hasMany(UserEntitlement::class);
    }

    /**
     * Whether the account has a password that can sign in. An account that the provider created has none, and the
     * takeover of an unverified account removes it (#1385).
     */
    final public function hasPassword(): bool
    {
        return is_string($this->password) && $this->password !== '';
    }

    /**
     * Whether the address of the account was never verified (ADR 0059): nobody proved that they read its mailbox. Only such
     * an account is taken over by the first proof of an address. A verified account keeps its verified address through a
     * change of the address, which applies after the new address is confirmed (ADR 0058): an account that is not verified
     * now was never verified. A query may select some columns only: a column that the model does not hold counts as null.
     */
    final public function addressWasNeverVerified(): bool
    {
        return ($this->getAttributes()['email_verified_at'] ?? null) === null;
    }

    /**
     * Whether the account has this address now, without regard to the case (the unique index of the address ignores the case)
     */
    final public function hasAddress(string $address): bool
    {
        $email = $this->getAttributes()['email'] ?? null;

        return is_string($email) && mb_strtolower($email) === mb_strtolower(trim($address));
    }

    /**
     * The version of the sessions of the account (ADR 0059). A new account holds 0: the column defaults to 0, and a model
     * built in memory does not hold it yet.
     */
    final public function sessionVersion(): int
    {
        $value = $this->getAttributes()['session_version'] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Marks the address as verified, in one conditional write (#1706, ADR 0058): only while the stored row still has the
     * address of this model and no verified address. A model that was read before a change of the address, or before
     * another proof verified it, writes nothing. Returns whether it wrote; the model holds the new values only then.
     */
    final public function markEmailAsVerified(): bool
    {
        $address = $this->getRawOriginal('email');
        if (! $this->exists || ! is_string($address)) {
            return false;
        }

        $now = $this->freshTimestamp();
        $written = static::query()
            ->whereKey($this->getKey())
            ->where('email', $address)
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => $now, 'updated_at' => $now]) === 1;
        if ($written) {
            $this->forceFill(['email_verified_at' => $now, 'updated_at' => $now]);
            $this->syncOriginalAttributes(['email_verified_at', 'updated_at']);
        }

        return $written;
    }

    /**
     * The mail of the address check, for "Resend verification email" of an account whose address was never verified: the
     * sign-up makes a verified account (ADR 0065), and a change or a correction of the address waits for its own link
     * (#1706, ADR 0058). Its link
     * carries the page of a community that the session of the request holds, if any (#1612, ADR 0057): the link lands
     * there, also in another browser.
     */
    final public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailAddress(Landing::fromSession()));
    }

    /**
     * The password reset mail goes through the queue, encrypted, as the other mails that hold a link (ADR 0064)
     *
     * @param  string  $token
     */
    final public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }

    /**
     * Every update of the row by a model also needs the version of the sessions that the model read (ADR 0059). A takeover
     * adds 1 to the version on the locked row, so a model that a request read before it (a request of the session of
     * before, which passed the session check a moment before the takeover) writes nothing after it: no error, and the row
     * keeps the values of the takeover. This holds for every writer: the actions of the application, and the framework (the
     * password that the sign-in or "Log out other browser sessions" hashes again, the remember token, the second factor).
     * A model that does not hold the column (a query that selected some columns only) is not checked.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSaveQuery($query)
    {
        parent::setKeysForSaveQuery($query);
        if (array_key_exists('session_version', $this->original)) {
            $query->where('session_version', '=', $this->getRawOriginal('session_version'));
        }

        return $query;
    }

    /**
     * The deletion of the account is no update: it finds the row by its key only, as the framework does. The deletion with
     * a password checks the version of the sessions on the locked row first (DeleteUser::delete())
     */
    protected function performDeleteOnModel()
    {
        /** @var Builder<static> $query */
        $query = $this->newModelQuery();
        parent::setKeysForSaveQuery($query)->delete();

        $this->exists = false;
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
