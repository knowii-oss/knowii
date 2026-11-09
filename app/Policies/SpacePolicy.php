<?php

namespace App\Policies;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\Space;
use App\Models\User;
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

        return $user !== null && $user->belongsToCommunity($space->community);
    }

    /**
     * Whether the user can add content to the space (posts, messages, pages), following its posting policy.
     */
    final public function contribute(User $user, Space $space): bool
    {
        $community = $space->community;

        if (! $user->belongsToCommunity($community)) {
            return false;
        }

        if ($space->posting_policy === KnowiiSpacePostingPolicy::Members) {
            return true;
        }

        return self::isModerator($user, $space);
    }

    /**
     * Whether the user moderates the space: community owner, admins and moderators.
     */
    final public function moderate(User $user, Space $space): bool
    {
        return self::isModerator($user, $space);
    }

    private static function isModerator(User $user, Space $space): bool
    {
        $community = $space->community;

        return $user->ownsCommunity($community)
            || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Admin)
            || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Moderator);
    }
}
