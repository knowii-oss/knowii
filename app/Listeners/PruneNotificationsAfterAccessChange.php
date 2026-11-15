<?php

namespace App\Listeners;

use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Jobs\PruneUnviewableNotifications;
use App\Models\Community;

/**
 * Who can view a "groups" space changed (#1474, ADR 0042, design 6.8.4): an access group was created, changed or
 * deleted, a member joined or left one, or the roles of the community changed (the owner and the admins view every
 * space). The notifications of the members who lost the access go away. The work runs in a job on the queue
 * `notifications`. The listeners of the app are found by their `handle...` methods.
 */
class PruneNotificationsAfterAccessChange
{
    final public function handleAccessGroupsChanged(AccessGroupsChanged $event): void
    {
        $this->prune($event->communityCuid);
    }

    final public function handleCommunityMembersChanged(CommunityMembersChanged $event): void
    {
        $this->prune($event->communityCuid);
    }

    private function prune(string $communityCuid): void
    {
        $community = Community::query()->where('cuid', $communityCuid)->first();
        if ($community !== null) {
            PruneUnviewableNotifications::dispatch($community);
        }
    }
}
