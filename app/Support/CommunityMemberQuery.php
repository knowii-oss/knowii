<?php

namespace App\Support;

use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * The members of a community with their role, the owner included (#1242). Each user gets two extra attributes:
 * `community_role` (owner, admin, moderator or member) and `community_member_since`.
 */
final class CommunityMemberQuery
{
    /**
     * Owner first, then admins, moderators and members, each by username. An optional search keeps the members whose
     * username, or a word of their name, starts with the query (mention autocomplete, #1177).
     *
     * @return Builder<User>
     */
    public static function for(Community $community, string $search = ''): Builder
    {
        $query = User::query()
            ->leftJoin('community_members', function (JoinClause $join) use ($community) {
                $join->on('community_members.user_id', '=', 'users.id')->where('community_members.community_id', '=', $community->id);
            })
            ->where(function (Builder $scope) use ($community) {
                $scope->where('users.id', $community->owner_id)->orWhereNotNull('community_members.id');
            })
            ->select('users.*')
            ->selectRaw("case when users.id = ? then 'owner' else community_members.role end as community_role", [$community->owner_id])
            ->selectRaw('coalesce(community_members.created_at, ?) as community_member_since', [$community->created_at]);

        if ($search !== '') {
            // LIKE wildcards in the input are literal
            $prefix = addcslashes(mb_strtolower($search), '%_\\').'%';
            $query->where(function (Builder $scope) use ($prefix) {
                $scope->whereRaw('lower(users.username) like ?', [$prefix])
                    ->orWhereRaw('lower(users.name) like ?', [$prefix])
                    ->orWhereRaw('lower(users.name) like ?', ['% '.$prefix]);
            })->orderByRaw('lower(users.username) like ? desc', [$prefix]);
        }

        return self::ordered($query, $community);
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
    private static function ordered(Builder $query, Community $community): Builder
    {
        return $query
            ->orderByRaw("case when users.id = ? then 0 when community_members.role = 'admin' then 1 when community_members.role = 'moderator' then 2 else 3 end", [$community->owner_id])
            ->orderBy('users.username');
    }
}
