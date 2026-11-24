<?php

namespace App\Actions\SpaceGroups;

use App\Actions\AccessGroups\AccessGroupSpaceGroups;
use App\Constants;
use App\Contracts\SpaceGroups\SetsSpaceGroupAccessGroups;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\LiveConnections;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class SetSpaceGroupAccessGroups implements SetsSpaceGroupAccessGroups
{
    /**
     * Replace the access groups that open a whole space group (#1422, ADR 0041): the same link as
     * SetAccessGroupSpaceGroups, seen from the space group. The owner, the admins and the moderators with the "manage
     * spaces" right when they can read every "groups" space of the space group. An empty list removes every link.
     */
    final public function set(User $user, SpaceGroup $spaceGroup, array $input): SpaceGroup
    {
        $community = $spaceGroup->community;
        Gate::forUser($user)->authorize('manageSpaces', $community);

        $cuids = AccessGroupSpaceGroups::cuids($input, 'access_groups', Constants::MAX_ACCESS_GROUPS_PER_COMMUNITY);
        $ids = AccessGroupSpaceGroups::accessGroupIds($community, $cuids);

        /** @var list<int> $current */
        $current = $spaceGroup->accessGroups()->pluck('access_groups.id')->all();
        if (array_diff($ids, $current) !== [] || array_diff($current, $ids) !== []) {
            AccessGroupSpaceGroups::authorizeChange($user, $community, [$spaceGroup->id]);
        }

        $spaceGroup->accessGroups()->sync($ids);

        Log::info('Access groups of a space group set', ['spaceGroup' => $spaceGroup->cuid, 'access_groups' => $cuids]);
        AccessGroupSpaceGroups::broadcast($spaceGroup);
        // An access group that no longer opens the space group (ADR 0075)
        if (array_diff($current, $ids) !== []) {
            LiveConnections::endForMembersOf($community);
        }

        return $spaceGroup;
    }
}
