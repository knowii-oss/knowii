<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\RemovesCommunityMembers;
use App\Events\Communities\CommunityMemberRemoved;
use App\Models\Community;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RemoveCommunityMember implements RemovesCommunityMembers
{
    /**
     * Remove the member.
     */
    public function remove(User $user, Community $community, User $communityMember): void
    {
        $this->authorize($user, $community, $communityMember);

        $this->ensureUserDoesNotOwnCommunity($communityMember, $community);

        $community->removeUser($communityMember);

        CommunityMemberRemoved::dispatch($community, $communityMember);
    }

    /**
     * Authorize that the user can remove the member.
     */
    protected function authorize(User $user, Community $community, User $communityMember): void
    {
        if (! Gate::forUser($user)->check('removeCommunityMember', $community) &&
            $user->id !== $communityMember->id) {
            throw new AuthorizationException;
        }
    }

    /**
     * Ensure that the currently authenticated user does not own the community.
     */
    protected function ensureUserDoesNotOwnCommunity(User $communityMember, Community $community): void
    {
        if ($communityMember->id === $community->owner->id) {
            throw ValidationException::withMessages([
                'community' => [__('You may not leave a community that you created.')],
            ])->errorBag('removeCommunityMember');
        }
    }
}
