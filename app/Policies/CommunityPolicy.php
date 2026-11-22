<?php

namespace App\Policies;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
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
     * Change who can join the community (#1486, ADR 0044): owner and admins, like the rights of the moderators. The
     * action refuses a personal community: nobody joins it.
     */
    final public function manageJoinPolicy(User $user, Community $community): bool
    {
        return CommunityRoles::isOwnerOrAdmin($user, $community);
    }

    /**
     * Manage the invite links (#1391, ADR 0062): owner and admins, like who can join. A link opens the community to anyone
     * who holds it, and it can give paid groups. It covers the list, a read, a create, an update, a delete and the list of
     * who joined. The actions also check the rights to give groups and tags (InvitationGrants).
     */
    final public function manageInviteLinks(User $user, Community $community): bool
    {
        return CommunityRoles::isOwnerOrAdmin($user, $community);
    }

    /**
     * Let search engines index the public spaces of the community (#1487, ADR 0044): owner and admins, like who can
     * join. The action refuses a personal community: it has no public space.
     */
    final public function manageIndexing(User $user, Community $community): bool
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
     * Give the community to another member (#1339, ADR 0035): the owner, and never for a personal community.
     */
    final public function transferOwnership(User $user, Community $community): bool
    {
        return $user->ownsCommunity($community) && $community->visibility !== KnowiiCommunityVisibility::Personal;
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
     * Create, rename, recolor, change the visibility of and delete the member tags (#1389, ADR 0060): owner and admins.
     * Each of these acts at once on every member who has the tag.
     */
    final public function manageMemberTags(User $user, Community $community): bool
    {
        return CommunityRoles::isOwnerOrAdmin($user, $community);
    }

    /**
     * Put a member tag on a member and take it off (#1389): owner, admins, and moderators with the "manage members"
     * right. The role hierarchy of #1242 does not apply: a tag gives no right, so a moderator tags any member, themself
     * too. The invitations and the invite links that carry tags use the same rule (#1390, #1391).
     */
    final public function assignMemberTags(User $user, Community $community): bool
    {
        return CommunityRoles::allows($user, $community, KnowiiModeratorPermission::ManageMembers);
    }

    /**
     * See every member tag, those of the managers included, with their member counts (#1389): the same people as those
     * who assign them. Another member sees the tags of the members only (MemberTagsOfMembers).
     */
    final public function viewMemberTags(User $user, Community $community): bool
    {
        return CommunityRoles::allows($user, $community, KnowiiModeratorPermission::ManageMembers);
    }

    /**
     * Determine whether the user can delete the model.
     */
    final public function delete(User $user, Community $community): bool
    {
        return $user->ownsCommunity($community);
    }
}
