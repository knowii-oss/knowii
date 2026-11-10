<?php

namespace App\Policies;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiModeratorPermission;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\Space;
use App\Models\User;
use App\Support\CommunityRoles;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Who can read and write in a space (#1173). Managing spaces (create, update, delete, reorder) is a community
 * permission: CommunityPolicy::manageSpaces.
 */
class SpacePolicy
{
    use HandlesAuthorization;

    /**
     * Public spaces are readable by anyone (visitors get access in M2); other spaces by the community members only.
     */
    final public function view(?User $user, Space $space): bool
    {
        if ($space->visibility === KnowiiSpaceVisibility::Public) {
            return true;
        }

        if ($user === null || ! $user->belongsToCommunity($space->community)) {
            return false;
        }

        if ($space->visibility === KnowiiSpaceVisibility::Members) {
            return true;
        }

        // groups (#1241, ADR 0025): the owner and the admins see every space, to manage it; the others need a group
        return CommunityRoles::isOwnerOrAdmin($user, $space->community) || $user->isInAccessGroupOf($space);
    }

    /**
     * Whether the user can add content to the space (posts, messages, pages), following its posting policy.
     */
    final public function contribute(User $user, Space $space): bool
    {
        $community = $space->community;

        // A link space holds no content (#1294, ADR 0026)
        if ($space->type === KnowiiSpaceType::Link) {
            return false;
        }

        // A member who cannot read the space cannot write in it (groups visibility, #1241)
        if (! $user->belongsToCommunity($community) || ! $this->view($user, $space)) {
            return false;
        }

        if ($space->posting_policy === KnowiiSpacePostingPolicy::Members) {
            return true;
        }

        return self::isModerator($user, $space);
    }

    /**
     * Whether the user moderates the content of the space (deletes the posts and comments of others): owner, admins,
     * and moderators when the community gives them the "delete content" right (#1240, ADR 0017).
     */
    final public function moderate(User $user, Space $space): bool
    {
        // A moderator who cannot read a group space does not act on it (#1325, ADR 0025)
        return CommunityRoles::allows($user, $space->community, KnowiiModeratorPermission::DeleteContent) && $this->view($user, $space);
    }

    /**
     * Whether the user creates, edits, reorders and deletes the pages of a page space (#1179), or the lessons and
     * sections of a course space (#1188): the owner, the admins and the moderators who can read it.
     */
    final public function managePages(User $user, Space $space): bool
    {
        return $space->type->holdsPages()
            && $user->belongsToCommunity($space->community)
            && $this->view($user, $space)
            && self::isModerator($user, $space);
    }

    /**
     * Whether the user marks lessons as done and reads their progress (#1304, ADR 0034): members of the community who
     * can read the space. A signed-in outsider reads a public course without keeping a progress in it.
     */
    final public function trackProgress(User $user, Space $space): bool
    {
        return $user->belongsToCommunity($space->community) && $this->view($user, $space);
    }

    private static function isModerator(User $user, Space $space): bool
    {
        $community = $space->community;

        return $user->ownsCommunity($community)
            || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Admin)
            || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Moderator);
    }
}
