<?php

namespace App\Support;

use App\Constants;
use App\Enums\KnowiiMemberSort;
use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * The members of a community with their role, the owner included (#1242). Each user gets two extra attributes:
 * `community_role` (owner, admin, moderator or member) and `community_member_since`: the left join of the membership row
 * gives them, and the owner has no such row. The profiles come with one query for the whole page, with only the columns
 * that MemberWithRoleResource reads for the headline (#1688).
 *
 * One search and three sorts for every member list (#148, ADR 0056): the member directory, the member management, the
 * member fields and the mention list.
 */
final class CommunityMemberQuery
{
    /**
     * The members in the order of the sort. `role` (the default): owner first, then admins, moderators and members, each
     * by username. `name`: the name from A to Z without the case. `newest`: the date of joining, the newest first; the
     * owner has no membership row and joined at the date of the community. The username is unique, so it ends each order.
     *
     * An optional search keeps the members whose username, or a word of their name, starts with the text (mention
     * autocomplete, #1177). It reads the username and the name only, never a profile field or the email address. With
     * `role`, the usernames that start with the text come first; with the other sorts, the sort alone gives the order.
     *
     * @return Builder<User>
     */
    public static function for(Community $community, string $search = '', KnowiiMemberSort $sort = KnowiiMemberSort::Role): Builder
    {
        $query = User::query()
            ->with('profile:id,user_id,headline,field_visibility')
            ->leftJoin('community_members', function (JoinClause $join) use ($community) {
                $join->on('community_members.user_id', '=', 'users.id')->where('community_members.community_id', '=', $community->id);
            })
            // The database starts from the people of the community (the membership rows of its unique index, and the
            // owner), then reads their accounts by the primary key: it does not read every account of the instance. An
            // owner with a membership row is still listed one time
            ->whereRaw(
                'users.id in (select user_id from community_members where community_id = ? union all select cast(? as bigint))',
                [$community->id, $community->owner_id],
            )
            ->select('users.*')
            ->selectRaw("case when users.id = ? then 'owner' else community_members.role end as community_role", [$community->owner_id])
            ->selectRaw('coalesce(community_members.created_at, ?) as community_member_since', [$community->created_at]);

        $search = self::searchText($search);
        if ($search !== '') {
            // LIKE wildcards in the input are literal
            $prefix = addcslashes(mb_strtolower($search), '%_\\').'%';
            $query->where(function (Builder $scope) use ($prefix) {
                $scope->whereRaw('lower(users.username) like ?', [$prefix])
                    ->orWhereRaw('lower(users.name) like ?', [$prefix])
                    ->orWhereRaw('lower(users.name) like ?', ['% '.$prefix]);
            });
            if ($sort === KnowiiMemberSort::Role) {
                $query->orderByRaw('lower(users.username) like ? desc', [$prefix]);
            }
        }

        return self::ordered($query, $community, $sort);
    }

    /**
     * The search text of a member list, as the server reads it: trimmed, and cut to its first characters (#148). The
     * controllers give an empty text for a value that is not a text (`?query[]=a`).
     */
    public static function searchText(string $input): string
    {
        return trim(mb_substr(trim($input), 0, Constants::MEMBER_SEARCH_MAX_LENGTH));
    }

    /**
     * The team of a community: the owner, the admins and the moderators, in the same order (#1135).
     *
     * @return Builder<User>
     */
    public static function team(Community $community): Builder
    {
        return self::for($community)->where(function (Builder $scope) use ($community) {
            $scope->where('users.id', $community->owner_id)->orWhereIn('community_members.role', ['admin', 'moderator']);
        });
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function ordered(Builder $query, Community $community, KnowiiMemberSort $sort): Builder
    {
        $query = match ($sort) {
            KnowiiMemberSort::Role => $query->orderByRaw("case when users.id = ? then 0 when community_members.role = 'admin' then 1 when community_members.role = 'moderator' then 2 else 3 end", [$community->owner_id]),
            KnowiiMemberSort::Name => $query->orderByRaw('lower(users.name)'),
            KnowiiMemberSort::Newest => $query->orderByRaw('coalesce(community_members.created_at, ?) desc', [$community->created_at]),
        };

        return $query->orderBy('users.username');
    }
}
