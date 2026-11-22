<?php

namespace App\Support;

use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\BusinessException;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\MemberTag;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Gives the access groups and the member tags of an invitation (#1390, ADR 0061), and later of an invite link (#1391), to
 * a person who becomes a member. The caller calls it inside the transaction of the join, after the attach: a failure
 * fails the whole join, and nothing stays. The caller reads the groups and the tags in that transaction with a shared
 * lock (as AcceptCommunityInvitation, or InvitationGrants::lockPresent), so a group or a tag that is gone is not given.
 *
 * Groups go through AccessGroupMemberships::addManual (the membership is by hand, `manual`), tags through
 * MemberTagAssignments::add: the one writer of each table. A tag past the limit of 20 tags of a member is skipped with a
 * log line: the join never fails for a tag. A person who is a member already gets them too, and keeps their role.
 *
 * After the commit, when a group was given: AccessGroupsChanged and SpaceTreeChanged, one time each (the navigation of
 * the member shows the spaces of the groups). No broadcast for a tag (ADR 0060 decision 20).
 */
final class JoinGrants
{
    /**
     * @param  iterable<AccessGroup>  $accessGroups  Groups of the community
     * @param  iterable<MemberTag>  $memberTags  Tags of the community
     * @return bool Whether a group was given
     */
    public static function apply(Community $community, User $member, iterable $accessGroups, iterable $memberTags): bool
    {
        $gaveGroup = false;
        foreach ($accessGroups as $group) {
            // A group of another community never comes this way: the reader of the lists checks it (InvitationGrants)
            if ($group->community_id !== $community->id) {
                continue;
            }
            AccessGroupMemberships::addManual($group, $member);
            $gaveGroup = true;
        }

        foreach ($memberTags as $tag) {
            if ($tag->community_id !== $community->id) {
                continue;
            }
            $tag->setRelation('community', $community);
            try {
                MemberTagAssignments::add($tag, $member);
            } catch (BusinessException) {
                Log::warning('Member tag of the join skipped: the member has the most tags', ['community' => $community->cuid, 'tag' => $tag->cuid, 'member' => $member->cuid]);
            }
        }

        if ($gaveGroup) {
            // Both events wait for the commit of the join (ShouldDispatchAfterCommit), and are dropped by a rollback
            AccessGroupsChanged::dispatch($community->cuid);
            SpaceTreeChanged::dispatch($community->cuid);
        }

        return $gaveGroup;
    }
}
