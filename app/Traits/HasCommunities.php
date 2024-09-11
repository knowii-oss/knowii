<?php

namespace App\Traits;

use App\Knowii;
use App\Models\Community;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;
use Laravel\Sanctum\HasApiTokens;

trait HasCommunities
{

  /**
   * Get all of the communities the user owns or belongs to.
   *
   * @return Collection
   */
  public function allCommunities()
  {
    return $this->ownedCommunities->merge($this->communities)->sortBy('name');
  }

  /**
   * Get all of the communities the user owns.
   *
   * @return HasMany
   */
  public function ownedCommunities()
  {
    return $this->hasMany(Knowii::communityModel());
  }

  /**
   * Get all of the communities the user belongs to.
   *
   * @return BelongsToMany
   */
  public function communities()
  {
    return $this->belongsToMany(Knowii::communityModel(), Knowii::communityMembershipModel())
      ->withPivot('role')
      ->withTimestamps()
      ->as('membership');
  }

  /**
   * Get the user's "personal" community.
   *
   * @return Community
   */
  public function personalCommunity()
  {
    return $this->ownedCommunities->where('personal_community', true)->first();
  }

  /**
   * Determine if the user owns the given community.
   *
   * @param Community $community
   * @return bool
   */
  public function ownsCommunity(Community $community)
  {
    if (is_null($community)) {
      return false;
    }

    return $this->id == $community->{$this->getForeignKey()};
  }

  /**
   * Determine if the user belongs to the given community.
   *
   * @param Community $community
   * @return bool
   */
  public function belongsToCommunity(Community $community)
  {
    if (is_null($community)) {
      return false;
    }

    return $this->ownsCommunity($community) || $this->communities->contains(function ($t) use ($community) {
        return $t->id === $community->id;
      });
  }

  /**
   * Get the role that the user has in the community.
   *
   * @param Community $community
   * @return \Laravel\Jetstream\Role|null
   */
  public function communityRole(Community $community)
  {
    if ($this->ownsCommunity($community)) {
      return new OwnerRole;
    }

    if (!$this->belongsToCommunity($community)) {
      return;
    }

    $role = $community->users
      ->where('id', $this->id)
      ->first()
      ->membership
      ->role;

    return $role ? Jetstream::findRole($role) : null;
  }

  /**
   * Determine if the user has the given role in the given community.
   *
   * @param Community $community
   * @param string $role
   * @return bool
   */
  public function hasCommunityRole(Community $community, string $role)
  {
    if ($this->ownsCommunity($community)) {
      return true;
    }

    return $this->belongsToCommunity($community) && optional(Jetstream::findRole($community->users->where(
        'id', $this->id
      )->first()->membership->role))->key === $role;
  }

  /**
   * Get the user's permissions for the given community.
   *
   * @param Community $community
   * @return array
   */
  public function communityPermissions(Community $community)
  {
    if ($this->ownsCommunity($community)) {
      return ['*'];
    }

    if (!$this->belongsToCommunity($community)) {
      return [];
    }

    return (array)optional($this->communityRole($community))->permissions;
  }

  /**
   * Determine if the user has the given permission in the given community.
   *
   * @param Community $community
   * @param string $permission
   * @return bool
   */
  public function hasCommunityPermission(Community $community, string $permission)
  {
    if ($this->ownsCommunity($community)) {
      return true;
    }

    if (!$this->belongsToCommunity($community)) {
      return false;
    }

    if (in_array(HasApiTokens::class, class_uses_recursive($this)) &&
      !$this->tokenCan($permission) &&
      $this->currentAccessToken() !== null) {
      return false;
    }

    $permissions = $this->communityPermissions($community);

    return in_array($permission, $permissions) ||
      in_array('*', $permissions) ||
      (Str::endsWith($permission, ':create') && in_array('*:create', $permissions)) ||
      (Str::endsWith($permission, ':update') && in_array('*:update', $permissions));
  }
}
