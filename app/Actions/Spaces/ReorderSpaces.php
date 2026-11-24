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
    public const INCOMPLETE_LIST_MESSAGE = 'List every space of the group exactly once';

    /**
     * Set the order of the spaces of a space group. The input lists every space of the group once.
     *
     * A manager reorders the spaces of a group only when they can read all of them (SpacePolicy::view, as UpdateSpace
     * and DeleteSpace, ADR 0025). The refusal is the answer of a list that does not name every space once: a person
     * who cannot read a space of the group cannot list every space of it, and the answer then says nothing more.
     */
    final public function reorder(User $user, SpaceGroup $spaceGroup, array $input): Collection
    {
        $community = $spaceGroup->community;

        $gate = Gate::forUser($user);
        $gate->authorize('manageSpaces', $community);

        /** @var array{space_cuids: list<string>} $validated */
        $validated = Validator::make($input, [
            'space_cuids' => ['required', 'array'],
            'space_cuids.*' => ['required', 'string', 'distinct'],
        ])->validate();

        $spaces = $spaceGroup->spaces()->with(['accessGroups', 'spaceGroup.accessGroups'])->get()->keyBy('cuid');
        $requested = $validated['space_cuids'];

        $readsEverySpace = $spaces->every(static fn (Space $space): bool => $gate->check('view', $space->setRelation('community', $community)));

        if (! $readsEverySpace || count($requested) !== $spaces->count() || array_diff($requested, $spaces->keys()->all()) !== []) {
            throw ValidationException::withMessages([
                'space_cuids' => self::INCOMPLETE_LIST_MESSAGE,
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
