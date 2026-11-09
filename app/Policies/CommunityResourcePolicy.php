<?php

namespace App\Policies;

use App\Models\CommunityResource;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Gate;

/**
 * Who can read a resource of a resources space and comment it (#1034). Adding resources is SpacePolicy::contribute.
 */
class CommunityResourcePolicy
{
    use HandlesAuthorization;

    final public function view(?User $user, CommunityResource $resource): bool
    {
        return Gate::forUser($user)->check('view', $resource->space);
    }

    /**
     * Members who can read the space comment its resources, like posts (PostPolicy::comment).
     */
    final public function comment(User $user, CommunityResource $resource): bool
    {
        return $user->belongsToCommunity($resource->community) && Gate::forUser($user)->check('view', $resource->space);
    }

    /**
     * Start the capture again (ADR 0018): the curator, or a member who manages the spaces of the community
     */
    final public function capture(User $user, CommunityResource $resource): bool
    {
        if (! Gate::forUser($user)->check('view', $resource->space)) {
            return false;
        }

        $isCurator = $resource->curator_id !== null && $resource->curator_id === $user->profile->id;

        return $isCurator || Gate::forUser($user)->check('manageSpaces', $resource->community);
    }
}
