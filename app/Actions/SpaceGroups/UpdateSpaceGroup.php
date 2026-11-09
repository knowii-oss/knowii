<?php

namespace App\Actions\SpaceGroups;

use App\Constants;
use App\Contracts\SpaceGroups\UpdatesSpaceGroups;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UpdateSpaceGroup implements UpdatesSpaceGroups
{
    /**
     * Rename a space group.
     */
    final public function update(User $user, SpaceGroup $spaceGroup, array $input): SpaceGroup
    {
        Log::info('Processing request to update a space group', ['spaceGroup' => $spaceGroup->cuid]);

        Gate::forUser($user)->authorize('manageSpaces', $spaceGroup->community);

        /** @var array{name: string} $validated */
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'min:'.Constants::MIN_LENGTH_SPACE_GROUP_NAME, 'max:'.Constants::MAX_LENGTH_SPACE_GROUP_NAME],
        ])->validate();

        $spaceGroup->update(['name' => trim($validated['name'])]);

        SpaceTreeChanged::dispatch($spaceGroup->community->cuid);

        return $spaceGroup;
    }
}
