<?php

namespace App\Support;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiModeratorPermission;
use App\Models\Community;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * The role rules of ADR 0017 (#1240), shared by the policies: the owner and the admins have every moderator right;
 * a moderator has the rights that the community switched on.
 */
final class CommunityRoles
{
    /**
     * The admin status of members of a community, known for the length of one callback (see withKnownAdmins), by
     * community id and user id. Empty outside a callback: the rule then reads the database, as always.
     *
     * @var array<int, array<int, bool>>
     */
    private static array $knownAdmins = [];

    /**
     * Run the callback with the admin status of the given members of a community already known, so that the check of
     * many members (the fan-out of a notification, #1475) does not ask the database once per member. The status is
     * read now, with one query for all the members, and forgotten when the callback ends.
     *
     * @template TReturn
     *
     * @param  list<int>  $userIds
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function withKnownAdmins(Community $community, array $userIds, Closure $callback)
    {
        $before = self::$knownAdmins[$community->id] ?? null;
        // Nested calls for the same members (a check inside a check) do not ask again
        if ($before !== null && array_diff_key(array_flip($userIds), $before) === []) {
            return $callback();
        }

        $roles = DB::table('community_members')->where('community_id', $community->id)->whereIn('user_id', $userIds)->pluck('role', 'user_id');
        $known = [];
        foreach ($userIds as $userId) {
            $known[$userId] = $roles->get($userId) === KnowiiCommunityMemberRole::Admin->value;
        }

        self::$knownAdmins[$community->id] = $known + ($before ?? []);

        try {
            return $callback();
        } finally {
            if ($before === null) {
                unset(self::$knownAdmins[$community->id]);
            } else {
                self::$knownAdmins[$community->id] = $before;
            }
        }
    }

    public static function isOwnerOrAdmin(User $user, Community $community): bool
    {
        $known = self::$knownAdmins[$community->id][$user->id] ?? null;
        if ($known !== null) {
            return $user->ownsCommunity($community) || ($known && $user->belongsToCommunity($community));
        }

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
