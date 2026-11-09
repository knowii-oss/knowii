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
}
