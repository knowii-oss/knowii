<?php

namespace App\Actions\InviteLinks;

use App\Contracts\InviteLinks\JoinsCommunitiesThroughInviteLinks;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiInviteLinkStatus;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Exceptions\BusinessException;
use App\Exceptions\InviteLinkUnusableException;
use App\Models\Community;
use App\Models\InviteLink;
use App\Models\InviteLinkUse;
use App\Models\User;
use App\Support\EntitlementReads;
use App\Support\InviteLinkTexts;
use App\Support\JoinGrants;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JoinCommunityThroughInviteLink implements JoinsCommunitiesThroughInviteLinks
{
    /**
     * A signed-in person joins a community through an invite link (#1391, ADR 0062), as a member, in a community by
     * invitation as in an open one. It does not go through JoinCommunity, which refuses a community that is not open.
     *
     * The checks, in this order: a verified email address; a token that finds a link; then, in one transaction that locks
     * the community (`for key share`, as the insert of the membership takes it anyway) and then the link, always in this
     * order as the accept of an invitation: a link that works (on, before its end, below its maximum) of a community that
     * is not personal; a person who is not a member (the owner counts); a person whom no manager removed (a link does not
     * lift the block). Then the person becomes a member, the count of the link goes up by one, the join is recorded, and
     * the groups and the tags of the link are given (JoinGrants). A group that a rule on an entitlement feeds at that
     * moment is not given.
     *
     * A link that does not work, whatever the reason, gives InviteLinkUnusableException. A member or a removed person
     * gets a BusinessException, and nothing changes: no group, no tag, no count, no row.
     *
     * @throws BusinessException
     * @throws InviteLinkUnusableException
     */
    final public function join(User $user, string $token): Community
    {
        self::ensureVerified($user);

        return self::joinThrough($user, InviteLink::findByToken($token));
    }

    /**
     * The same join, for the sha256 of the token (the page of a link, whose session keeps the hash only)
     *
     * @throws BusinessException
     * @throws InviteLinkUnusableException
     */
    final public function joinWithTokenHash(User $user, string $tokenHash): Community
    {
        self::ensureVerified($user);

        return self::joinThrough($user, InviteLink::findByTokenHash($tokenHash));
    }

    /**
     * @throws BusinessException
     */
    private static function ensureVerified(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            throw new BusinessException(InviteLinkTexts::VERIFY_EMAIL);
        }
    }

    /**
     * @throws BusinessException
     * @throws InviteLinkUnusableException
     */
    private static function joinThrough(User $user, ?InviteLink $link): Community
    {
        if ($link === null) {
            throw new InviteLinkUnusableException;
        }

        try {
            // 3 attempts: a deadlock or a serialization failure runs the join again. The closure writes only rows; the
            // events of the join wait for the commit
            $community = DB::transaction(static function () use ($user, $link): Community {
                $community = Community::query()->whereKey($link->community_id)->lock('for key share')->first();
                // The link as it is stored now, locked until the commit: two joins at the last use take it one after the other
                $locked = InviteLink::query()->whereKey($link->id)->lockForUpdate()->first();
                if ($community === null || $locked === null || $community->visibility === KnowiiCommunityVisibility::Personal || $locked->status() !== KnowiiInviteLinkStatus::Active) {
                    throw new InviteLinkUnusableException;
                }

                // The memberships as they are stored now (a join that committed meanwhile, or a new attempt)
                $user->unsetRelation('communities');
                if ($user->belongsToCommunity($community)) {
                    throw new BusinessException(InviteLinkTexts::ALREADY_MEMBER);
                }
                if ($community->joinBlocks()->where('user_id', $user->id)->exists()) {
                    throw new BusinessException(InviteLinkTexts::BLOCKED);
                }

                $community->users()->attach($user, ['role' => KnowiiCommunityMemberRole::Member]);
                // Only the join writes the count; the time of the last change of the link stays
                InviteLink::query()->whereKey($locked->id)->toBase()->increment('uses_count');
                InviteLinkUse::query()->create(['invite_link_id' => $locked->id, 'user_id' => $user->id]);

                // Read in the transaction with a shared lock: a group or a tag deleted meanwhile is skipped. A group that a
                // rule fed after the save of the link is not given
                $groups = $locked->accessGroups()->whereDoesntHave('rules')->sharedLock()->get();
                $tags = $locked->memberTags()->sharedLock()->get();
                JoinGrants::apply($community, $user, $groups, $tags);

                return $community;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            // The same person joined at the same moment by another way in (another link, an invitation): a member now
            throw new BusinessException(InviteLinkTexts::ALREADY_MEMBER);
        }

        // The memberships of this request are loaded again: the person is a member from now on
        $user->unsetRelation('communities');

        Log::info('Member joined the community through an invite link', ['community' => $community->cuid, 'link' => $link->cuid, 'member' => $user->cuid]);
        CommunityMembersChanged::dispatch($community->cuid);
        // A community with entitlement rules gives the person their groups at once (#1386, ADR 0047)
        EntitlementReads::afterJoin($user, $community);

        return $community;
    }
}
