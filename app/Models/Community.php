<?php

namespace App\Models;

use App\Enums\KnowiiCommunityVisibility;
use App\Events\Communities\CommunityCreated;
use App\Events\Communities\CommunityDeleted;
use App\Events\Communities\CommunityUpdated;
use App\Http\Resources\CommunityResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\CommunityFactory;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * App\Models\Community
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property string $name
 * @property string $slug
 * @property ?string $description
 * @property KnowiiCommunityVisibility $visibility
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int $owner_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SpaceGroup> $spaceGroups
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Space> $spaces
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CommunityInvitation> $communityInvitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CommunityResource> $communityResources
 * @property-read ?int $community_invitations_count
 * @property-read User $owner
 * @property-read ?CommunityMember $communityMember
 * @property-read ?int $users_count
 * @property-read ?int $space_groups_count
 * @property-read ?int $spaces_count
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
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\CommunityResource>(class-string<TResource>|null $resourceClass = null)
 *
 * @mixin \Eloquent
 */
#[UseResource(CommunityResource::class)]
class Community extends Model
{
    // Automatically generate cuid2 for the model
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
     * @return BelongsToMany<User, covariant $this, CommunityMember, 'communityMember'>
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
     * Get the space groups of the community, in navigation order.
     *
     * @return HasMany<SpaceGroup, covariant $this>
     */
    final public function spaceGroups(): HasMany
    {
        return $this->hasMany(SpaceGroup::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Get all the spaces of the community.
     *
     * @return HasMany<Space, covariant $this>
     */
    final public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    /**
     * Get the resources of all the resources spaces of the community (ADR 0015).
     *
     * @return HasMany<\App\Models\CommunityResource, covariant $this>
     */
    final public function communityResources(): HasMany
    {
        // The model, not the API resource class imported above
        return $this->hasMany(\App\Models\CommunityResource::class);
    }

    /**
     * The 10 most recent resources of the given spaces (the resources spaces that a member can see).
     *
     * @param  array<int, int>  $spaceIds
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\CommunityResource>
     */
    final public function recentResources(array $spaceIds): \Illuminate\Database\Eloquent\Collection
    {
        return $this->communityResources()
            ->whereIn('space_id', $spaceIds)
            ->with(['resource', 'curator', 'space'])
            ->latest()
            ->latest('id')
            ->limit(10)
            ->get();
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
