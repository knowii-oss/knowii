<?php

namespace App\Support;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiModeratorPermission;
use App\Models\Community;
use App\Models\User;

/**
 * The role rules of ADR 0017 (#1240), shared by the policies: the owner and the admins have every moderator right;
 * a moderator has the rights that the community switched on.
 */
final class CommunityRoles
{
    public static function isOwnerOrAdmin(User $user, Community $community): bool
    {
        return $user->ownsCommunity($community) || $user->hasCommunityRole($community, KnowiiCommunityMemberRole::Admin);
    }

    public static function allows(User $user, Community $community, KnowiiModeratorPermission $permission): bool
    {
        return self::isOwnerOrAdmin($user, $community)
            || ($user->hasCommunityRole($community, KnowiiCommunityMemberRole::Moderator) && $community->moderatorsCan($permission));
    }

    /**
     * The name of the role of a user in a community: owner, admin, moderator, member, or null for a non-member.
     */
    public static function roleName(User $user, Community $community): ?string
    {
        if ($user->ownsCommunity($community)) {
            return 'owner';
        }

        return $community->getMemberById($user->id)?->role->value;
    }

    /**
     * The rank of a role in the hierarchy of #1242: owner 4, admin 3, moderator 2, member 1. Null is the owner.
     */
    public static function rankOf(?KnowiiCommunityMemberRole $role): int
    {
        return match ($role) {
            null => 4,
            KnowiiCommunityMemberRole::Admin => 3,
            KnowiiCommunityMemberRole::Moderator => 2,
            KnowiiCommunityMemberRole::Member => 1,
        };
    }

    /**
     * The rank of a user in a community: 0 when they are not a member.
     */
    public static function rankOfUser(User $user, Community $community): int
    {
        if ($user->ownsCommunity($community)) {
            return self::rankOf(null);
        }

        $member = $community->getMemberById($user->id);

        return $member === null ? 0 : self::rankOf($member->role);
    }

    /**
     * The role hierarchy of member management (#1242, ADR 0017): with the "manage members" right, a user acts only on
     * members whose role is lower than their own (null target: the owner, whom nobody changes or removes).
     */
    public static function canActOn(User $user, Community $community, ?KnowiiCommunityMemberRole $targetRole): bool
    {
        return self::allows($user, $community, KnowiiModeratorPermission::ManageMembers)
            && self::rankOf($targetRole) < self::rankOfUser($user, $community);
    }

    /**
     * A user with the "manage members" right gives only roles lower than their own: only the owner makes admins.
     */
    public static function canGrant(User $user, Community $community, KnowiiCommunityMemberRole $role): bool
    {
        return self::allows($user, $community, KnowiiModeratorPermission::ManageMembers)
            && self::rankOf($role) < self::rankOfUser($user, $community);
    }
}
