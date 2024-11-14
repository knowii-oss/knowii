<?php

namespace App\Traits;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\CommunityMember;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait HasCommunities
{
    /**
     * Get all of the communities the user owns or belongs to.
     */
    final public function allCommunities(): Collection
    {
        return $this->ownedCommunities->merge($this->communities)->sortBy('name');
    }

    /**
     * Get all of the communities the user owns.
     *
     * @return HasMany
     */
    final public function ownedCommunities()
    {
        return $this->hasMany(Community::class);
    }

    /**
     * Get all of the communities the user belongs to.
     *
     * @return BelongsToMany
     */
    final public function communities()
    {
        return $this->belongsToMany(Community::class, CommunityMember::class)
            ->withPivot('role')
            ->withTimestamps()
            ->as('communityMember');
    }

    /**
     * Get the user's "personal" community.
     */
    final public function personalCommunity(): Community
    {
        return $this->ownedCommunities->where('visibility', KnowiiCommunityVisibility::Personal)->first();
    }

    /**
     * Determine if the user owns the given community.
     */
    final public function ownsCommunity(Community $community): bool
    {
        if (is_null($community)) {
            return false;
        }

        return $this->id == $community->owner_id;
    }

    /**
     * Determine if the user belongs to the given community.
     *
     * @return bool
     */
    final public function belongsToCommunity(Community $community)
    {
        if (is_null($community)) {
            return false;
        }

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

        return $community->users->where('id', $this->id)->first()->communityMember->role === $role;
    }
}
