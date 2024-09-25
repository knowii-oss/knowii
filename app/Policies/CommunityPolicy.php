<?php

namespace App\Policies;

use App\Models\Community;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Enums\KnowiiCommunityMemberRole;

class CommunityPolicy
{
  use HandlesAuthorization;

  /**
   * Determine whether the user can view any models.
   */
  public function viewAny(User $user): bool
  {
    // TODO allow for platform admins
    return false;
  }

  /**
   * Determine whether the user can view the model.
   */
  final public function view(User $user, Community $community): bool
  {
    return $user->belongsToCommunity($community);
  }

  /**
   * Determine whether the user can create models.
   */
  final public function create(User $user): bool
  {
    return true;
  }

  /**
   * Determine whether the user can update the model.
   */
  final public function update(User $user, Community $community): bool
  {
    return $user->ownsCommunity($community);
  }

  /**
   * Determine whether the user can add members.
   */
  final public function addCommunityMember(User $user, Community $community): bool
  {
    return $user->ownsCommunity($community);
  }

  /**
   * Determine whether the user can update member permissions.
   */
  final public function updateCommunityMember(User $user, Community $community): bool
  {
    return $user->ownsCommunity($community);
  }

  /**
   * Determine whether the user can remove members.
   */
  final public function removeCommunityMember(User $user, Community $community): bool
  {
    return $user->ownsCommunity($community);
  }

  /**
   * Determine whether the user can delete the model.
   */
  final public function delete(User $user, Community $community): bool
  {
    return $user->ownsCommunity($community);
  }

  /**
   * Determine whether the user can create resource collections.
   */
  final public function createResourceCollection(User $user, Community $community): bool
  {
    return $user->ownsCommunity($community) 
        || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Admin) 
        || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Moderator);
  }

  /**
   * Determine whether the user can update resource collections.
   */
  final public function updateResourceCollection(User $user, Community $community): bool
  {
    return $user->ownsCommunity($community) 
        || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Admin) 
        || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Moderator);
  }

  /**
   * Determine whether the user can delete resource collections.
   */
  final public function deleteResourceCollection(User $user, Community $community): bool
  {
    return $user->ownsCommunity($community) 
        || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Admin) 
        || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Moderator);
  }

  /**
   * Determine whether the user can create resources.
   */
  final public function createResource(User $user, Community $community): bool
  {
    return $user->ownsCommunity($community) 
        || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Admin) 
        || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Moderator);
  }

  /**
   * Determine whether the user can update resources.
   */
  final public function updateResource(User $user, Community $community): bool
  {
    return $user->ownsCommunity($community) 
        || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Admin) 
        || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Moderator);
  }

  /**
   * Determine whether the user can delete resources.
   */
  final public function deleteResource(User $user, Community $community): bool
  {
    return $user->ownsCommunity($community) 
        || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Admin) 
        || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Moderator);
  }
}
