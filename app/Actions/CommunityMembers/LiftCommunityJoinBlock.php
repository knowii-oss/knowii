<?php

namespace App\Actions\CommunityMembers;

use App\Contracts\CommunityMembers\LiftsCommunityJoinBlocks;
use App\Models\Community;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class LiftCommunityJoinBlock implements LiftsCommunityJoinBlocks
{
    /**
     * "May join again" (#1486): a manager lets a person whom a manager removed join the community by themselves again.
     * Owner, admins, and moderators with the "manage members" right, like the other member actions. A person without a
     * block gives a 404.
     *
     * @throws AuthorizationException
     */
    final public function lift(User $user, Community $community, string $memberCuid): void
    {
        Gate::forUser($user)->authorize('removeCommunityMember', $community);

        $deleted = $community->joinBlocks()
            ->whereIn('user_id', User::query()->select('id')->where('cuid', $memberCuid))
            ->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException)->setModel(User::class, [$memberCuid]);
        }

        Log::info('Community join block lifted', ['community' => $community->cuid, 'member' => $memberCuid]);
    }
}
