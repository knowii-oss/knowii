<?php

namespace App\Actions\SpaceGroups;

use App\Constants;
use App\Contracts\SpaceGroups\CreatesSpaceGroups;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\Positions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CreateSpaceGroup implements CreatesSpaceGroups
{
    /**
     * Create a space group at the end of the community navigation.
     */
    final public function create(User $user, Community $community, array $input): SpaceGroup
    {
        Log::info('Processing request to create a space group', ['community' => $community->cuid]);

        Gate::forUser($user)->authorize('manageSpaces', $community);

        /** @var array{name: string} $validated */
        $validated = Validator::make($input, [
            // WARNING: The fields MUST also match the ones listed in SpaceGroupApiController
            'name' => ['required', 'string', 'min:'.Constants::MIN_LENGTH_SPACE_GROUP_NAME, 'max:'.Constants::MAX_LENGTH_SPACE_GROUP_NAME],
        ])->validate();

        if ($community->spaceGroups()->count() >= Constants::MAX_SPACE_GROUPS_PER_COMMUNITY) {
            throw new BusinessException('A community can have at most '.Constants::MAX_SPACE_GROUPS_PER_COMMUNITY.' space groups');
        }

        $spaceGroup = DB::transaction(function () use ($community, $validated) {
            $position = Positions::next($community->spaceGroups());

            /** @var SpaceGroup */
            return $community->spaceGroups()->create([
                'name' => trim($validated['name']),
                'position' => $position,
            ]);
        });

        SpaceTreeChanged::dispatch($community->cuid);

        Log::info('Space group created', ['spaceGroup' => $spaceGroup->cuid]);

        return $spaceGroup;
    }
}
