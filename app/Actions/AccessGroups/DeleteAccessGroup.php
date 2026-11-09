<?php

namespace App\Actions\AccessGroups;

use App\Contracts\AccessGroups\DeletesAccessGroups;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class DeleteAccessGroup implements DeletesAccessGroups
{
    /**
     * Delete an access group (#1241): its members lose the spaces it opened. A "groups" space without any group left
     * stays visible to the owner and the admins only.
     */
    final public function delete(User $user, AccessGroup $group): void
    {
        $community = $group->community;
        Gate::forUser($user)->authorize('manageAccessGroups', $community);

        $group->delete();

        Log::info('Access group deleted', ['community' => $community->cuid, 'group' => $group->cuid]);
        AccessGroupsChanged::dispatch($community->cuid);
        SpaceTreeChanged::dispatch($community->cuid);
    }
}
