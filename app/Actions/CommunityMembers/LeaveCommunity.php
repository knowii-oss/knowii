<?php

namespace App\Actions\CommunityMembers;

use App\Contracts\CommunityMembers\LeavesCommunities;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class LeaveCommunity implements LeavesCommunities
{
    /**
     * A member leaves the community (#1338). They also leave its access groups and lose their course progress, like a
     * removed member (Community::removeUser). What they wrote stays in the community, and they lose the rights on it
     * (authorization audit, rule 2).
     *
     * The owner cannot leave: a community always has an owner. They transfer the ownership first (#1339) or delete
     * the community.
     *
     * @throws AuthorizationException
     * @throws BusinessException
     */
    final public function leave(User $user, Community $community): void
    {
        if ($user->ownsCommunity($community)) {
            throw new BusinessException('The owner cannot leave the community. Transfer the ownership first, or delete the community');
        }

        Gate::forUser($user)->authorize('leave', $community);

        $community->removeUser($user);

        Log::info('Member left the community', ['community' => $community->cuid, 'member' => $user->cuid]);
        CommunityMembersChanged::dispatch($community->cuid);
    }
}
