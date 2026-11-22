<?php

namespace App\Actions\CommunityMembers;

use App\Contracts\CommunityMembers\AcceptsCommunityInvitations;
use App\Enums\KnowiiCommunityVisibility;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityJoinBlock;
use App\Models\User;
use App\Support\EntitlementReads;
use App\Support\JoinGrants;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcceptCommunityInvitation implements AcceptsCommunityInvitations
{
    public const string EXPIRED = 'This invitation expired. Ask for a new one';

    /**
     * The signed-in user joins the community with the role of the invitation (#1242). The invitation is for one email
     * address: a forwarded link does not work for another account. An expired invitation must be sent again. A personal
     * community has no other member: its invitation does not work.
     *
     * The access groups and the member tags of the invitation are given in the transaction of the join (#1390),
     * also to a person who is a member already, who keeps their role (JoinGrants). The transaction locks the
     * community, then reads the invitation again and locks it: the role, the groups and the tags are the ones stored at
     * the join. An invitation that is gone meanwhile (cancelled, or replaced by a new one) gives the answer of a link
     * of an invitation that is gone (404); one that expired meanwhile gives the answer of an expired invitation.
     *
     * @throws BusinessException
     * @throws ModelNotFoundException<CommunityInvitation>
     */
    final public function accept(User $user, CommunityInvitation $invitation): Community
    {
        $community = $invitation->community;

        if (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            throw new BusinessException('This invitation is for another email address. Sign in with the account of '.$invitation->email);
        }

        if ($invitation->isExpired()) {
            throw new BusinessException(self::EXPIRED);
        }

        if ($community->visibility === KnowiiCommunityVisibility::Personal) {
            throw new BusinessException('A personal community has no other members');
        }

        // 3 attempts: a deadlock or a serialization failure runs the join again. The closure writes only rows; the
        // events of the join wait for the commit
        DB::transaction(static function () use ($user, $community, $invitation) {
            // The community first, `for key share` as the insert of the membership takes it anyway, then the invitation.
            // A deletion of the community or of an account can still take some rows in another order; the attempts of
            // this transaction cover it
            Community::query()->whereKey($community->id)->lock('for key share')->first(['id']);
            // The invitation as it is stored now, locked until the commit
            $locked = CommunityInvitation::query()->whereKey($invitation->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw (new ModelNotFoundException)->setModel(CommunityInvitation::class, [$invitation->cuid]);
            }
            if ($locked->isExpired()) {
                throw new BusinessException(self::EXPIRED);
            }

            // Read in the transaction with a shared lock: a group or a tag deleted meanwhile is skipped, and one that is
            // read stays until the commit
            $groups = $locked->accessGroups()->sharedLock()->get();
            $tags = $locked->memberTags()->sharedLock()->get();

            // The memberships as they are stored now (a join that committed meanwhile, or a new attempt)
            $user->unsetRelation('communities');
            if (! $user->belongsToCommunity($community)) {
                $community->users()->attach($user, ['role' => $locked->role]);
            }
            JoinGrants::apply($community, $user, $groups, $tags);
            // A person whom a manager removed joins again with an invitation: the block ends (#1486)
            CommunityJoinBlock::query()->where('community_id', $community->id)->where('user_id', $user->id)->delete();
            $locked->delete();
        }, 3);

        Log::info('Community invitation accepted', ['community' => $community->cuid, 'member' => $user->cuid]);
        CommunityMembersChanged::dispatch($community->cuid);
        // A community with entitlement rules gives the person their groups at once (#1386, ADR 0047)
        EntitlementReads::afterJoin($user, $community);

        return $community;
    }
}
