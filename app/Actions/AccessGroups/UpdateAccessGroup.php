<?php

namespace App\Actions\AccessGroups;

use App\Contracts\AccessGroups\UpdatesAccessGroups;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateAccessGroup implements UpdatesAccessGroups
{
    /**
     * Rename an access group or change its description (#1241): the owner and the admins.
     */
    final public function update(User $user, AccessGroup $group, array $input): AccessGroup
    {
        $community = $group->community;
        Gate::forUser($user)->authorize('manageAccessGroups', $community);

        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }
        /** @var array{name?: string, description?: string|null} $validated */
        $validated = Validator::make($input, AccessGroupInputRules::editable($community, false, $group->id))->validate();

        $changes = [];
        if (array_key_exists('name', $validated)) {
            $changes['name'] = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $changes['description'] = AccessGroupInputRules::blankToNull($validated['description']);
        }
        $group->update($changes);

        AccessGroupsChanged::dispatch($community->cuid);
        // The space dialog shows the group names
        SpaceTreeChanged::dispatch($community->cuid);

        return $group;
    }
}
