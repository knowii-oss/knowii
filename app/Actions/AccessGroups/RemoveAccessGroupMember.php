<?php

namespace App\Actions\AccessGroups;

use App\Contracts\AccessGroups\RemovesAccessGroupMembers;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\User;
use App\Support\AccessGroupMemberships;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class RemoveAccessGroupMember implements RemovesAccessGroupMembers
{
    /**
     * Remove a member from an access group (#1241): they lose the spaces that only this group opened. A member who is
     * not in the group gives a 404. Only the "by hand" source goes (#1386, ADR 0047): a member whom an entitlement rule
     * gives the group stays in it, and the answer is the same.
     */
    final public function remove(User $user, AccessGroup $group, string $memberCuid): void
    {
        $community = $group->community;
        Gate::forUser($user)->authorize('manageAccessGroupMembers', $community);

        $member = $group->members()->where('users.cuid', $memberCuid)->first();
        $outcome = $member === null ? null : AccessGroupMemberships::removeManual($group, $member);
        if ($outcome === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$memberCuid]);
        }

        // Only the `manual` flag goes. A member whom a rule gives the group stays in it (#1386, ADR 0047)
        Log::info('Access group member removed', ['group' => $group->cuid, 'member' => $memberCuid, 'kept_by_rule' => $outcome === AccessGroupMemberships::KEPT_BY_RULE]);
        AccessGroupsChanged::dispatch($community->cuid);
        SpaceTreeChanged::dispatch($community->cuid);
    }
}
