<?php

namespace App\Models;

use App\Enums\KnowiiCommunityVisibility;
use App\Events\Communities\CommunityCreated;
use App\Events\Communities\CommunityDeleted;
use App\Events\Communities\CommunityUpdated;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Parables\Cuid\GeneratesCuid;

class Community extends Model
{
    // Automatically generate cuid2 for the model
    // Reference: https://github.com/Parables/laravel-cuid2
    use GeneratesCuid;

    use HasFactory;

    // Automatically generate slugs
    // Reference: https://github.com/cviebrock/eloquent-sluggable
    use Sluggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
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
     * @var array<int, string>
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
     */
    final public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    /**
     * Get all of the users including the owner.
     *
     * @return Collection
     */
    final public function allUsers()
    {
        return $this->users->merge([$this->owner]);
    }

    /**
     * Get all of the users that belong to the community.
     *
     * @return BelongsToMany
     */
    final public function users()
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
     */
    final public function communityResourceCollections(): HasMany
    {
        return $this->hasMany(CommunityResourceCollection::class);
    }

    /**
     * Get the 10 most recent resources across all communityResourceCollections.
     *
     * @return \Illuminate\Database\Eloquent\Collection
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
