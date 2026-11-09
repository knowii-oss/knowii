<?php

namespace App\Actions\SpaceGroups;

use App\Contracts\SpaceGroups\ReordersSpaceGroups;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Community;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReorderSpaceGroups implements ReordersSpaceGroups
{
    /**
     * Set the order of all the space groups of a community. The input lists every group of the community once.
     */
    final public function reorder(User $user, Community $community, array $input): Collection
    {
        Gate::forUser($user)->authorize('manageSpaces', $community);

        /** @var array{space_group_cuids: list<string>} $validated */
        $validated = Validator::make($input, [
            'space_group_cuids' => ['required', 'array'],
            'space_group_cuids.*' => ['required', 'string', 'distinct'],
        ])->validate();

        $groups = $community->spaceGroups()->get()->keyBy('cuid');
        $requested = $validated['space_group_cuids'];

        if (count($requested) !== $groups->count() || array_diff($requested, $groups->keys()->all()) !== []) {
            throw ValidationException::withMessages([
                'space_group_cuids' => 'List every space group of the community exactly once',
            ]);
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
