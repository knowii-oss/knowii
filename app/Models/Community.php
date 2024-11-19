<?php

namespace App\Models;

use App\Enums\KnowiiCommunityVisibility;
use App\Events\Communities\CommunityCreated;
use App\Events\Communities\CommunityDeleted;
use App\Events\Communities\CommunityUpdated;
use Carbon\Carbon;
use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\CommunityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Parables\Cuid\GeneratesCuid;

/**
 * App\Models\Community
 *
 * @property int $id
 * @property string $cuid
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property KnowiiCommunityVisibility $visibility
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, CommunityResourceCollection> $communityResourceCollections
 * @property-read Collection<int, User> $users
 * @property int $owner_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CommunityInvitation> $communityInvitations
 * @property-read int|null $community_invitations_count
 * @property-read int|null $community_resource_collections_count
 * @property-read \App\Models\User $owner
 * @property-read \App\Models\CommunityMember|null $communityMember
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\CommunityFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community findSimilarSlugs(string $attribute, array<mixed> $config, string $slug)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community whereCuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community whereVisibility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Community withUniqueSlugConstraints(\Illuminate\Database\Eloquent\Model $model, string $attribute, array<mixed> $config, string $slug)
 *
 * @mixin \Eloquent
 */
class Community extends Model
{
    // Automatically generate cuid2 for the model
    // Reference: https://github.com/Parables/laravel-cuid2
    use GeneratesCuid;

    /** @use HasFactory<CommunityFactory> */
    use HasFactory;

    // Automatically generate slugs
    // Reference: https://github.com/cviebrock/eloquent-sluggable
    use Sluggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
        'name',
        'slug',
        'description',
        'visibility',
        'owner_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'invitations',
        'owner_id',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => CommunityCreated::class,
        'updated' => CommunityUpdated::class,
        'deleted' => CommunityDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'visibility' => KnowiiCommunityVisibility::class,
        ];
    }

    /**
     * Get the owner.
     *
     * @return BelongsTo<User, covariant $this>
     */
    final public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    /**
     * Get all of the users including the owner.
     *
     * @return Collection<int, User>
     */
    final public function allUsers(): Collection
    {
        return $this->users->merge([$this->owner]);
    }

    // FIXME should it be BelongsToMany<User or CommunityMember ?
    /**
     * Get all of the users that belong to the community.
     *
     * @return BelongsToMany<User, covariant $this>
     */
    final public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, CommunityMember::class)
            ->withPivot('role')
            ->withTimestamps()
            ->as('communityMember');
    }

    /**
     * Determine if the given user belongs to the community.
     */
    final public function hasUser(User $user): bool
    {
        return $this->users->contains($user) || $user->ownsCommunity($this);
    }

    /**
     * Get a community member by their user ID.
     */
    final public function getMemberById(int $userId): ?CommunityMember
    {
        return CommunityMember::query()
            ->where('community_id', $this->id)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Determine if the given email address belongs to a user in the community.
     */
    final public function hasUserWithEmail(string $email): bool
    {
        return $this->allUsers()->contains(function ($user) use ($email) {
            return $user->email === $email;
        });
    }

    /**
     * Get all of the pending user invitations for the community.
     *
     * @return HasMany<CommunityResourceCollection, covariant $this>
     */
    final public function communityResourceCollections(): HasMany
    {
        return $this->hasMany(CommunityResourceCollection::class);
    }

    /**
     * Get the 10 most recent resources across all communityResourceCollections.
     *
     * @return Collection<int, CommunityResourceCollection>
     */
    final public function recentResources(): Collection
    {
        return $this->communityResourceCollections()
            ->with(['communityResources', 'communityResources.resource', 'communityResources.curator', 'communityResources.collection'])
            ->get()
            ->pluck('communityResources')
            ->flatten()
            ->sortByDesc('created_at')
            ->take(10);
    }

    /**
     * Get all of the pending user invitations for the community.
     *
     * @return HasMany<CommunityInvitation, covariant $this>
     */
    final public function communityInvitations(): HasMany
    {
        return $this->hasMany(CommunityInvitation::class);
    }

    /**
     * Remove the given user from the community.
     */
    final public function removeUser(User $user): void
    {
        $this->users()->detach($user);
    }

    /**
     * Purge all of the community's resources.
     */
    final public function purge(): void
    {
        $this->users()->detach();
        $this->delete();
    }

    /**
     * Return the sluggable configuration array for this model.
     * Reference: https://github.com/cviebrock/eloquent-sluggable
     *
     * @return array<string,array<string,string>>
     */
    final public function sluggable(): array
    {
        // The slug is derived from the name (and uniqueness is ensured)
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
