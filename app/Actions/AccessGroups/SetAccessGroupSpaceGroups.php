<?php

namespace App\Actions\AccessGroups;

use App\Constants;
use App\Contracts\AccessGroups\SetsAccessGroupSpaceGroups;
use App\Models\AccessGroup;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class SetAccessGroupSpaceGroups implements SetsAccessGroupSpaceGroups
{
    /**
     * Replace the space groups that an access group opens whole (#1422, ADR 0041). The members of the group then read
     * every space of those space groups that has the "groups" visibility, the spaces added later included. The people
     * who can give a space its groups can do it: the owner, the admins and the moderators with the "manage spaces"
     * right, for the space groups whose "groups" spaces they can all read. An empty list removes every link.
     */
    final public function set(User $user, AccessGroup $group, array $input): AccessGroup
    {
        $community = $group->community;
        Gate::forUser($user)->authorize('manageSpaces', $community);

        $cuids = AccessGroupSpaceGroups::cuids($input, 'space_groups', Constants::MAX_SPACE_GROUPS_PER_COMMUNITY);
        $ids = AccessGroupSpaceGroups::spaceGroupIds($community, $cuids);

        /** @var list<int> $current */
        $current = $group->spaceGroups()->pluck('space_groups.id')->all();
        // Only the space groups that gain or lose the group count: the others do not change
        AccessGroupSpaceGroups::authorizeChange($user, $community, [...array_diff($ids, $current), ...array_diff($current, $ids)]);

        $group->spaceGroups()->sync($ids);

        Log::info('Space groups of an access group set', ['group' => $group->cuid, 'space_groups' => $cuids]);
        AccessGroupSpaceGroups::broadcast($group);

        return $group;
    }
}
