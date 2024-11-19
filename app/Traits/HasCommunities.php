<?php

namespace App\Traits;

use App\Enums\KnowiiCommunityMemberRole;
use App\Models\Community;
use App\Models\CommunityMember;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property-read Collection<int, Community> $allCommunities
 * @property-read Collection<int, Community> $ownedCommunities
 * @property-read Collection<int, Community> $communities
 * @property Community $personalCommunity
 */
trait HasCommunities
{
    /**
     * Get all of the communities the user owns or belongs to.
     *
     * @return Collection<Community>
     */
    final public function allCommunities(): Collection
    {
        return $this->ownedCommunities->merge($this->communities)->sortBy('name');
    }

    /**
     * Get all of the communities the user owns.
     *
     * @return HasMany<Community, covariant $this>
     */
    final public function ownedCommunities(): HasMany
    {
        return $this->hasMany(Community::class, 'owner_id');
    }

    /**
     * Get all of the communities the user belongs to.
     *
     * @return BelongsToMany<Community, covariant $this>
     */
    final public function communities(): BelongsToMany
    {
        return $this->belongsToMany(Community::class, CommunityMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Determine if the user owns the given community.
     */
    final public function ownsCommunity(Community $community): bool
    {
        return $this->id == $community->owner_id;
    }

    /**
     * Determine if the user belongs to the given community.
     */
    final public function belongsToCommunity(Community $community): bool
    {
        return $this->ownsCommunity($community) || $this->communities->contains(function ($t) use ($community) {
            return $t->id === $community->id;
        });
    }

    /**
     * Determine if the user has the given role in the given community.
     */
    final public function hasCommunityRole(Community $community, KnowiiCommunityMemberRole $role): bool
    {
        if (! $this->belongsToCommunity($community)) {
            return false;
        }

        if ($this->ownsCommunity($community)) {
            return true;
        }

        $communityMember = $community->getMemberById($this->id);

        if (! $communityMember) {
            return false;
        }

        return $communityMember->role === $role;
    }
}
