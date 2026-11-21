<?php

namespace App\Support;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiSpaceVisibility;
use Closure;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;

/**
 * The reading rule of a space for a member, as SpacePolicy::view decides it, written once for the database (#1515, ADR
 * 0053): the owner of the community or a row in `community_members`; then a space that is not "groups", or the owner, an
 * admin, or a member of an access group of the space or of its space group (ADR 0025, ADR 0041). A person who is not a
 * member never passes, also in a public space: the answers that count and "My events" are for members.
 *
 * Two uses, one rule: `EventAnswer::scopeOfReaders` (the user is a column of each row of the answers) and
 * `Event::scopeReadableBy` (one user). EventAnswersTest and UserEventsApiTest hold each use together with the policy.
 */
final class SpaceReaders
{
    /**
     * A condition for `whereExists`: the space whose id is in the column `$spaceColumn` of the outer query is readable by
     * the user. `$user` is the column of the outer query that holds the user id (a string), or the id of one user.
     *
     * @return Closure(QueryBuilder): void
     */
    public static function exists(string $spaceColumn, string|int $user): Closure
    {
        // The user as a column of the outer query, or as one value
        $isUser = static function (QueryBuilder $query, string $column, string $boolean = 'and') use ($user): void {
            is_string($user) ? $query->whereColumn($column, '=', $user, $boolean) : $query->where($column, '=', $user, $boolean);
        };

        return static function (QueryBuilder $space) use ($spaceColumn, $isUser): void {
            $space->selectRaw('1')
                ->from('spaces')
                ->join('communities', 'communities.id', '=', 'spaces.community_id')
                ->leftJoin('community_members', static function (JoinClause $join) use ($isUser): void {
                    $join->on('community_members.community_id', '=', 'communities.id');
                    $isUser($join, 'community_members.user_id');
                })
                ->whereColumn('spaces.id', $spaceColumn)
                // A member of the community
                ->where(static function (QueryBuilder $member) use ($isUser): void {
                    $isUser($member, 'communities.owner_id');
                    $member->orWhereNotNull('community_members.id');
                })
                // Who can read the space
                ->where(static function (QueryBuilder $reader) use ($isUser): void {
                    $reader->whereIn('spaces.visibility', [KnowiiSpaceVisibility::Public->value, KnowiiSpaceVisibility::Members->value]);
                    $isUser($reader, 'communities.owner_id', 'or');
                    $reader->orWhere('community_members.role', KnowiiCommunityMemberRole::Admin->value)
                        ->orWhereExists(static function (QueryBuilder $group) use ($isUser): void {
                            $group->selectRaw('1')->from('access_group_members');
                            $isUser($group, 'access_group_members.user_id');
                            $group->where(static function (QueryBuilder $opening): void {
                                $opening->whereIn('access_group_members.access_group_id', static function (QueryBuilder $ofSpace): void {
                                    $ofSpace->select('access_group_id')->from('access_group_space')->whereColumn('access_group_space.space_id', 'spaces.id');
                                })->orWhereIn('access_group_members.access_group_id', static function (QueryBuilder $ofSpaceGroup): void {
                                    $ofSpaceGroup->select('access_group_id')->from('access_group_space_group')->whereColumn('access_group_space_group.space_group_id', 'spaces.space_group_id');
                                });
                            });
                        });
                });
        };
    }
}
