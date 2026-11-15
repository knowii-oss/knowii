<?php

namespace App\Actions\CommunityMembers;

use App\Contracts\CommunityMembers\RemovesCommunityMembers;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Models\Community;
use App\Models\CommunityJoinBlock;
use App\Models\User;
use App\Support\CommunityRoles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class RemoveCommunityMember implements RemovesCommunityMembers
{
    use FindsCommunityMembers;

    /**
     * Remove a member, within the role hierarchy (#1242, ADR 0017). What they wrote stays in the community. A manager
     * removed them: they cannot join the community again by themselves, even when it is open, until an invitation that
     * they accept or a manager lifts it (#1486). A member who leaves by themselves keeps that freedom.
     *
     * @throws AuthorizationException
     */
    final public function remove(User $user, Community $community, string $memberCuid): void
    {
        Gate::forUser($user)->authorize('removeCommunityMember', $community);

        [$target, $currentRole] = $this->findMember($community, $memberCuid);

        if (! CommunityRoles::canActOn($user, $community, $currentRole)) {
            throw new AuthorizationException('You cannot remove this member');
        }

        DB::transaction(static function () use ($community, $target) {
            $community->removeUser($target);
            CommunityJoinBlock::query()->firstOrCreate(['community_id' => $community->id, 'user_id' => $target->id]);
        });

        Log::info('Community member removed', ['community' => $community->cuid, 'member' => $target->cuid]);
        CommunityMembersChanged::dispatch($community->cuid);
    }
}
