<?php

namespace App\Support;

use App\Models\AccessGroup;
use App\Models\AccessGroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of `access_group_members` (#1386, ADR 0047). A membership has one row per member and group, and two
 * flags: `manual` (added by hand or by the API) and `by_rule` (an entitlement rule gives it). The row is deleted when both
 * are false. A hand change never touches `by_rule`, a rule change never touches `manual`, so a member added by hand stays
 * when the entitlement ends, and removing a rule never removes a member added by hand.
 *
 * Every change locks the row first, so two changes at once (a hand removal and a sync) cannot lose one of the flags.
 */
final class AccessGroupMemberships
{
    public const string REMOVED = 'removed';

    public const string KEPT_BY_RULE = 'kept_by_rule';

    /**
     * Add a member by hand. Adding twice changes nothing. A member who is in the group by a rule gets the `manual` flag too.
     */
    public static function addManual(AccessGroup $group, User $member): void
    {
        AccessGroupMember::query()->upsert(
            [['access_group_id' => $group->id, 'user_id' => $member->id, 'manual' => true, 'by_rule' => false]],
            ['access_group_id', 'user_id'],
            ['manual'],
        );
    }

    /**
     * Remove a member by hand: only the `manual` flag goes. When a rule still gives the group, the member stays in it
     * (`KEPT_BY_RULE`). Null when the member is not in the group at all, or not by hand and not by a rule either.
     *
     * @return self::REMOVED|self::KEPT_BY_RULE|null
     */
    public static function removeManual(AccessGroup $group, User $member): ?string
    {
        return DB::transaction(static function () use ($group, $member): ?string {
            $row = AccessGroupMember::query()->where('access_group_id', $group->id)->where('user_id', $member->id)->lockForUpdate()->first();
            if ($row === null) {
                return null;
            }
            if ($row->by_rule) {
                if ($row->manual) {
                    $row->forceFill(['manual' => false])->save();
                }

                return self::KEPT_BY_RULE;
            }
            $row->delete();

            return self::REMOVED;
        });
    }

    /**
     * Make the rule memberships of a member (by user id) in some groups (the groups of one community) the wanted ones: set `by_rule` on
     * the wanted groups, clear it elsewhere, delete the rows that nothing holds any more. Never touches `manual`.
     * Returns whether anything changed.
     *
     * @param  int  $userId  The member
     * @param  list<int>  $groupIds  All the groups of the community
     * @param  list<int>  $wantedGroupIds  The groups that a rule gives, a subset of `$groupIds`
     */
    public static function setByRule(int $userId, array $groupIds, array $wantedGroupIds): bool
    {
        return DB::transaction(static function () use ($userId, $groupIds, $wantedGroupIds): bool {
            $changed = false;
            /** @var array<int, AccessGroupMember> $rows */
            $rows = [];
            foreach (AccessGroupMember::query()->where('user_id', $userId)->whereIn('access_group_id', $groupIds)->lockForUpdate()->get() as $existing) {
                $rows[$existing->access_group_id] = $existing;
            }

            foreach ($wantedGroupIds as $groupId) {
                $row = $rows[$groupId] ?? null;
                if ($row !== null && $row->by_rule) {
                    continue;
                }
                $changed = true;
                // The row may appear between the read and now (a hand add at the same time): the conflict only sets the flag
                AccessGroupMember::query()->upsert(
                    [['access_group_id' => $groupId, 'user_id' => $userId, 'manual' => $row !== null && $row->manual, 'by_rule' => true]],
                    ['access_group_id', 'user_id'],
                    ['by_rule'],
                );
            }

            foreach ($rows as $groupId => $row) {
                if (! $row->by_rule || in_array($groupId, $wantedGroupIds, true)) {
                    continue;
                }
                $changed = true;
                self::releaseByRule($row);
            }

            return $changed;
        });
    }

    /**
     * Clear the `by_rule` flag of a locked row: the row goes when no hand flag is left
     */
    public static function releaseByRule(AccessGroupMember $row): void
    {
        if ($row->manual) {
            $row->forceFill(['by_rule' => false])->save();
        } else {
            $row->delete();
        }
    }
}
