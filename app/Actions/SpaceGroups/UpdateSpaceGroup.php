<?php

namespace App\Actions\SpaceGroups;

use App\Constants;
use App\Contracts\SpaceGroups\UpdatesSpaceGroups;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\PlainText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UpdateSpaceGroup implements UpdatesSpaceGroups
{
    public const UNREADABLE_SPACE_MESSAGE = 'You change a space group only when you can read every space of it';

    /**
     * Rename a space group. A manager renames a group only when they can read every space of it, as for the reorder of
     * its spaces (ReorderSpaces, ADR 0025).
     *
     * @throws AuthorizationException
     */
    final public function update(User $user, SpaceGroup $spaceGroup, array $input): SpaceGroup
    {
        Log::info('Processing request to update a space group', ['spaceGroup' => $spaceGroup->cuid]);

        $gate = Gate::forUser($user);
        $gate->authorize('manageSpaces', $spaceGroup->community);
        self::authorizeEverySpace($gate, $spaceGroup);

        PlainText::clean($input, ['name']);
        /** @var array{name: string} $validated */
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'min:'.Constants::MIN_LENGTH_SPACE_GROUP_NAME, 'max:'.Constants::MAX_LENGTH_SPACE_GROUP_NAME],
        ])->validate();

        $spaceGroup->update(['name' => trim($validated['name'])]);

        SpaceTreeChanged::dispatch($spaceGroup->community->cuid);

        return $spaceGroup;
    }

    /**
     * Refuse when the manager cannot read a space of the group (SpacePolicy::view). Also used by ReorderSpaceGroups.
     *
     * @throws AuthorizationException
     */
    public static function authorizeEverySpace(GateContract $gate, SpaceGroup $spaceGroup): void
    {
        $community = $spaceGroup->community;
        $readsEverySpace = $spaceGroup->spaces()->with(['accessGroups', 'spaceGroup.accessGroups'])->get()
            ->every(static fn (Space $space): bool => $gate->check('view', $space->setRelation('community', $community)));

        if (! $readsEverySpace) {
            throw new AuthorizationException(self::UNREADABLE_SPACE_MESSAGE);
        }
    }
}
