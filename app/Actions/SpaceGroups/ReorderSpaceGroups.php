<?php

namespace App\Actions\SpaceGroups;

use App\Constants;
use App\Contracts\SpaceGroups\ReordersSpaceGroups;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Community;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReorderSpaceGroups implements ReordersSpaceGroups
{
    /**
     * Set the order of all the space groups of a community. The input lists every group of the community once.
     *
     * A manager moves a group only when they can read every space of it (UpdateSpaceGroup::authorizeEverySpace): the
     * other groups move around a group that holds a space that they cannot read.
     *
     * @throws AuthorizationException
     */
    final public function reorder(User $user, Community $community, array $input): Collection
    {
        $gate = Gate::forUser($user);
        $gate->authorize('manageSpaces', $community);

        /** @var array{space_group_cuids: list<string>} $validated */
        $validated = Validator::make($input, [
            'space_group_cuids' => ['required', 'array', 'max:'.Constants::MAX_SPACE_GROUPS_PER_COMMUNITY],
            'space_group_cuids.*' => ['required', 'string', 'distinct'],
        ])->validate();

        $groups = $community->spaceGroups()->get()->keyBy('cuid');
        $requested = $validated['space_group_cuids'];

        if (count($requested) !== $groups->count() || array_diff($requested, $groups->keys()->all()) !== []) {
            throw ValidationException::withMessages([
                'space_group_cuids' => 'List every space group of the community exactly once',
            ]);
        }

        // A group moves when its place among the groups changes
        $current = $groups->keys()->all();
        foreach ($requested as $index => $cuid) {
            if (($current[$index] ?? null) !== $cuid) {
                /** @var SpaceGroup $group */
                $group = $groups->get($cuid);
                UpdateSpaceGroup::authorizeEverySpace($gate, $group->setRelation('community', $community));
            }
        }

        DB::transaction(function () use ($requested, $groups) {
            foreach ($requested as $position => $cuid) {
                /** @var SpaceGroup $group */
                $group = $groups->get($cuid);
                $group->update(['position' => $position]);
            }
        });

        SpaceTreeChanged::dispatch($community->cuid);

        return $community->spaceGroups()->get();
    }
}
