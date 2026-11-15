<?php

namespace App\Actions\CommunityMembers;

use App\Contracts\CommunityMembers\AcceptsCommunityInvitations;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityJoinBlock;
use App\Models\User;
use App\Support\EntitlementReads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcceptCommunityInvitation implements AcceptsCommunityInvitations
{
    /**
     * The signed-in user joins the community with the role of the invitation (#1242). The invitation is for one email
     * address: a forwarded link does not work for another account. An expired invitation must be sent again.
     *
     * @throws BusinessException
     */
    final public function accept(User $user, CommunityInvitation $invitation): Community
    {
        $community = $invitation->community;

        if (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            throw new BusinessException('This invitation is for another email address. Sign in with the account of '.$invitation->email);
        }

        if ($invitation->isExpired()) {
            throw new BusinessException('This invitation expired. Ask for a new one');
        }

        DB::transaction(static function () use ($user, $community, $invitation) {
            if (! $user->belongsToCommunity($community)) {
                $community->users()->attach($user, ['role' => $invitation->role]);
            }
            // A person whom a manager removed joins again with an invitation: the block ends (#1486)
            CommunityJoinBlock::query()->where('community_id', $community->id)->where('user_id', $user->id)->delete();
            $invitation->delete();
        });

        Log::info('Community invitation accepted', ['community' => $community->cuid, 'member' => $user->cuid]);
        CommunityMembersChanged::dispatch($community->cuid);
        // A community with entitlement rules gives the person their groups at once (#1386, ADR 0047)
        EntitlementReads::afterJoin($user, $community);

        return $community;
    }
}
