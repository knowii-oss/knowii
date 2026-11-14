<?php

namespace App\Policies;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiModeratorPermission;
use App\Models\Community;
use App\Models\User;
use App\Support\CommunityRoles;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommunityPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
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
     * Determine whether the user can manage the model.
     */
    final public function manage(User $user, Community $community): bool
    {
        return $user->ownsCommunity($community)
          || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Admin);
    }

    /**
     * Create, update, reorder and delete the space groups and spaces (#1173): owner, admins, and moderators when the
     * community gives them the "manage spaces" right (#1240, ADR 0017).
     */
    final public function manageSpaces(User $user, Community $community): bool
    {
        return CommunityRoles::allows($user, $community, KnowiiModeratorPermission::ManageSpaces);
    }

    /**
     * Switch the rights of the moderators (#1240): owner and admins.
     */
    final public function manageModeratorPermissions(User $user, Community $community): bool
    {
        return CommunityRoles::isOwnerOrAdmin($user, $community);
    }

    /**
     * Members upload images for their posts and comments (#1178).
     */
    final public function uploadImage(User $user, Community $community): bool
    {
        return $user->belongsToCommunity($community);
    }

    /**
     * Add members: owner, admins, and moderators with the "manage members" right (#1240). The role hierarchy (who
     * can act on whom) is checked by the member actions (#1242).
     */
    final public function addCommunityMember(User $user, Community $community): bool
    {
        return CommunityRoles::allows($user, $community, KnowiiModeratorPermission::ManageMembers);
    }

    /**
     * Change the role of members: same rule as adding them (#1240, #1242).
     */
    final public function updateCommunityMember(User $user, Community $community): bool
    {
        return CommunityRoles::allows($user, $community, KnowiiModeratorPermission::ManageMembers);
    }

    /**
     * Remove members: same rule as adding them (#1240, #1242).
     */
    final public function removeCommunityMember(User $user, Community $community): bool
    {
        return CommunityRoles::allows($user, $community, KnowiiModeratorPermission::ManageMembers);
    }

    /**
     * Leave the community (#1338): every member except the owner, who transfers the ownership first.
     */
    final public function leave(User $user, Community $community): bool
    {
        return $user->belongsToCommunity($community) && ! $user->ownsCommunity($community);
    }

    /**
     * Create, rename and delete access groups (#1241, ADR 0025): owner and admins. Groups decide paid access.
     */
    final public function manageAccessGroups(User $user, Community $community): bool
    {
        return CommunityRoles::isOwnerOrAdmin($user, $community);
    }

    /**
     * Add members to access groups and remove them (#1241): owner, admins, and moderators with the "manage members"
     * right.
     */
    final public function manageAccessGroupMembers(User $user, Community $community): bool
    {
        return CommunityRoles::allows($user, $community, KnowiiModeratorPermission::ManageMembers);
    }

    /**
     * See the access groups (#1241): those who manage their members, and those who manage spaces (to give a space its
     * groups).
     */
    final public function viewAccessGroups(User $user, Community $community): bool
    {
        return $this->manageAccessGroupMembers($user, $community) || $this->manageSpaces($user, $community);
    }

    /**
     * Determine whether the user can delete the model.
     */
    final public function delete(User $user, Community $community): bool
    {
        return $user->ownsCommunity($community);
    }
}
