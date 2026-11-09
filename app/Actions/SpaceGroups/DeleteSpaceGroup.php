<?php

namespace App\Actions\SpaceGroups;

use App\Contracts\SpaceGroups\DeletesSpaceGroups;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\BusinessException;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class DeleteSpaceGroup implements DeletesSpaceGroups
{
    /**
     * Delete an empty space group. A group that still has spaces is refused: its spaces must be moved or deleted
     * first, so that no content disappears by accident.
     */
    final public function delete(User $user, SpaceGroup $spaceGroup): void
    {
        Log::info('Processing request to delete a space group', ['spaceGroup' => $spaceGroup->cuid]);

        $community = $spaceGroup->community;

        Gate::forUser($user)->authorize('manageSpaces', $community);

        if ($spaceGroup->spaces()->exists()) {
            throw new BusinessException('Move or delete the spaces of this group before you delete it');
        }

        $spaceGroup->delete();

        SpaceTreeChanged::dispatch($community->cuid);
    }
}
