<?php

namespace App\Actions\Spaces;

use App\Contracts\Spaces\ReordersSpaces;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReorderSpaces implements ReordersSpaces
{
    /**
     * Set the order of the spaces of a space group. The input lists every space of the group once.
     */
    final public function reorder(User $user, SpaceGroup $spaceGroup, array $input): Collection
    {
        $community = $spaceGroup->community;

        Gate::forUser($user)->authorize('manageSpaces', $community);

        /** @var array{space_cuids: list<string>} $validated */
        $validated = Validator::make($input, [
            'space_cuids' => ['required', 'array'],
            'space_cuids.*' => ['required', 'string', 'distinct'],
        ])->validate();

        $spaces = $spaceGroup->spaces()->get()->keyBy('cuid');
        $requested = $validated['space_cuids'];

        if (count($requested) !== $spaces->count() || array_diff($requested, $spaces->keys()->all()) !== []) {
            throw ValidationException::withMessages([
                'space_cuids' => 'List every space of the group exactly once',
            ]);
        }

        DB::transaction(function () use ($requested, $spaces) {
            foreach ($requested as $position => $cuid) {
                /** @var Space $space */
                $space = $spaces->get($cuid);
                $space->update(['position' => $position]);
            }
        });

        SpaceTreeChanged::dispatch($community->cuid);

        return $spaceGroup->spaces()->get();
    }
}
