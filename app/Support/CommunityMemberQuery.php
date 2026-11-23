<?php

namespace App\Support;

use App\Constants;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiMemberSort;
use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Gate;

/**
 * The members of a community with their role, the owner included (#1242). Each user gets two extra attributes:
 * `community_role` (owner, admin, moderator or member) and `community_member_since`: the left join of the membership row
 * gives them, and the owner has no such row. The profiles come with one query for the whole page, with only the columns
 * that MemberWithRoleResource reads for the headline (#1688).
 *
 * One search and three sorts for every member list (#148, ADR 0056): the member directory, the member management, the
 * member fields and the mention list. Each list has a reader: the people who hide from the member list are left out for a
 * reader who cannot see them (#1466).
 */
final class CommunityMemberQuery
{
    /**
     * The roles of a membership row that put a person on the team, with the owner (#1135): always in the member list
     *
     * @var list<string>
     */
    private const array TEAM_ROLES = ['admin', 'moderator'];

    /**
     * The members in the order of the sort, as the reader may find them (#1466). `role` (the default): owner first, then
     * admins, moderators and members, each by username. `name`: the name from A to Z without the case. `newest`: the date
     * of joining, the newest first; the owner has no membership row and joined at the date of the community. The username
     * is unique, so it ends each order.
     *
     * A reader who cannot see the hidden members (CommunityPolicy::viewHiddenMembers) does not get the people who chose to
     * hide from the member list (`users.show_in_member_list`), except the team of the community (the owner, the admins and
     * the moderators) and the reader themself. One check of the reader for the query, and one condition in SQL: never a
     * query per member. Each user gets `community_in_member_list`: whether the other members find the person in this list.
     *
     * An optional search keeps the members whose username, or a word of their name, starts with the text (mention
     * autocomplete, #1177). It reads the username and the name only, never a profile field or the email address. With
     * `role`, the usernames that start with the text come first; with the other sorts, the sort alone gives the order.
     *
     * @return Builder<User>
     */
    public static function for(Community $community, User $reader, string $search = '', KnowiiMemberSort $sort = KnowiiMemberSort::Role): Builder
    {
        $query = self::members($community);

        if (! Gate::forUser($reader)->check('viewHiddenMembers', $community)) {
            $query->where(function (Builder $scope) use ($community, $reader) {
                $scope->where('users.show_in_member_list', true)
                    ->orWhere('users.id', $community->owner_id)
                    ->orWhereIn('community_members.role', self::TEAM_ROLES)
                    ->orWhere('users.id', $reader->id);
            });
        }

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
        // The team is always listed (#1466): it needs no reader
        $query = self::members($community)->where(function (Builder $scope) use ($community) {
            $scope->where('users.id', $community->owner_id)->orWhereIn('community_members.role', self::TEAM_ROLES);
        });

        return self::ordered($query, $community, KnowiiMemberSort::Role);
    }

    /**
     * Whether the reader is hidden from the member list of the community for the other members (#1466): the reader chose
     * to hide, and is not on the team. It reads the memberships that the request already loaded.
     */
    public static function hidesReader(Community $community, User $reader): bool
    {
        if ($reader->showsInMemberList() || $reader->ownsCommunity($community)) {
            return false;
        }

        $membership = $reader->communities->firstWhere('id', $community->id);
        $pivot = $membership?->relationLoaded('pivot') ? $membership->getRelation('pivot') : null;
        $role = $pivot instanceof Pivot ? $pivot->getAttribute('role') : null;

        return ! in_array($role instanceof KnowiiCommunityMemberRole ? $role->value : $role, self::TEAM_ROLES, true);
    }

    /**
     * The people of a community with their role, the date they joined and whether the other members find them in the
     * member list, with no order and no reader
     *
     * @return Builder<User>
     */
    private static function members(Community $community): Builder
    {
        return User::query()
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
            ->selectRaw('coalesce(community_members.created_at, ?) as community_member_since', [$community->created_at])
            // The team is always in the list; another person is when they chose it (#1466)
            ->selectRaw(
                '(users.show_in_member_list or users.id = ? or coalesce(community_members.role in (?, ?), false)) as community_in_member_list',
                [$community->owner_id, ...self::TEAM_ROLES],
            );
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
