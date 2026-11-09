<?php

namespace App\Actions\AccessGroups;

use App\Contracts\AccessGroups\RemovesAccessGroupMembers;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class RemoveAccessGroupMember implements RemovesAccessGroupMembers
{
    /**
     * Remove a member from an access group (#1241): they lose the spaces that only this group opened. A member who is
     * not in the group gives a 404.
     */
    final public function remove(User $user, AccessGroup $group, string $memberCuid): void
    {
        $community = $group->community;
        Gate::forUser($user)->authorize('manageAccessGroupMembers', $community);

        $member = $group->members()->where('users.cuid', $memberCuid)->first();
        if ($member === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$memberCuid]);
        }

        $group->members()->detach($member->id);

        Log::info('Access group member removed', ['group' => $group->cuid, 'member' => $memberCuid]);
        AccessGroupsChanged::dispatch($community->cuid);
        SpaceTreeChanged::dispatch($community->cuid);
    }
}
