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
}
