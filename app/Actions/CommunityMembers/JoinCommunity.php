<?php

namespace App\Actions\CommunityMembers;

use App\Contracts\CommunityMembers\JoinsCommunities;
use App\Enums\KnowiiCommunityMemberRole;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JoinCommunity implements JoinsCommunities
{
    /**
     * A signed-in person joins an open community by themselves, as a member (#1486, ADR 0044). It needs a verified email
     * address, a community that is open (never a personal one), a person who is not a member yet, and a person whom no
     * manager removed (CommunityJoinBlock): they ask the managers for an invitation instead.
     *
     * Joining gives no access group. The entitlement rules of a later issue run when a person becomes a member: the
     * members-changed event is their hook (the same event as an accepted invitation).
     *
     * @throws BusinessException
     */
    final public function join(User $user, Community $community): Community
    {
        if (! $user->hasVerifiedEmail()) {
            throw new BusinessException('Verify your email address before you join a community');
        }

        DB::transaction(function () use ($user, $community) {
            // Two requests at once (a double click) cannot add the person twice, nor pass a removal that happens now
            $locked = Community::query()->whereKey($community->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new BusinessException('This community is by invitation only. Ask its managers for an invitation');
            }

            if ($user->belongsToCommunity($locked)) {
                throw new BusinessException('You are already a member of this community');
            }

            if ($locked->joinBlocks()->where('user_id', $user->id)->exists()) {
                throw new BusinessException('You cannot join this community by yourself. Ask its managers for an invitation.');
            }

            $locked->users()->attach($user, ['role' => KnowiiCommunityMemberRole::Member]);
        });

        // The memberships of this request are loaded again: the person is a member from now on
        $user->unsetRelation('communities');

        Log::info('Member joined the community', ['community' => $community->cuid, 'member' => $user->cuid]);
        CommunityMembersChanged::dispatch($community->cuid);

        return $community;
    }
}
