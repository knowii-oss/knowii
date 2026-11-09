<?php

namespace App\Support;

use App\Models\Community;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The navigation of a community: its space groups and spaces, in order, limited to what the user may see.
 */
final class SpaceTree
{
    /**
     * @return Collection<int, SpaceGroup>
     */
    public static function for(Community $community, ?User $user): Collection
    {
        $canManage = $user !== null && Gate::forUser($user)->check('manageSpaces', $community);

        $groups = $community->spaceGroups()->with(['spaces'])->get();

        foreach ($groups as $group) {
            $visible = $group->spaces
                ->filter(function (Space $space) use ($community, $group, $user): bool {
                    // Avoid a query per space in the policy
                    $space->setRelation('community', $community);
                    $space->setRelation('spaceGroup', $group);

                    return Gate::forUser($user)->check('view', $space);
                })
                ->values();

            $group->setRelation('spaces', $visible);
        }

        // Members do not need empty groups. Managers see them, to fill them.
        return $canManage ? $groups : $groups->filter(fn (SpaceGroup $group) => $group->spaces->isNotEmpty())->values();
    }
}
