<?php

namespace App\Actions\CommunityMembers;

use App\Contracts\CommunityMembers\CancelsCommunityInvitations;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use App\Support\CommunityRoles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class CancelCommunityInvitation implements CancelsCommunityInvitations
{
    /**
     * Cancel a pending invitation: the same rule as giving its role (#1242).
     *
     * @throws AuthorizationException
     */
    final public function cancel(User $user, Community $community, CommunityInvitation $invitation): void
    {
        Gate::forUser($user)->authorize('addCommunityMember', $community);

        if (! CommunityRoles::canGrant($user, $community, $invitation->role)) {
            throw new AuthorizationException('You cannot cancel this invitation');
        }

        $invitation->delete();

        Log::info('Community invitation cancelled', ['community' => $community->cuid, 'invitation' => $invitation->cuid]);
        CommunityMembersChanged::dispatch($community->cuid);
    }
}
