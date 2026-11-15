<?php

namespace App\Contracts\Notifications;

use App\Models\Community;
use App\Models\Space;
use App\Models\User;

/**
 * Deletes the notification rows that must go when access or relations change (#1474, ADR 0042, design 6.8, 6.9).
 */
interface PrunesNotifications
{
    /**
     * The rows of the "groups" spaces of the community (or of one space) whose recipient cannot view the space any more.
     */
    public function forAccess(Community $community, ?Space $space = null): int;

    /**
     * The member leaves or is removed: all their rows in the community.
     */
    public function forMember(User $member, Community $community): int;

    /**
     * The blocker blocked the member: the rows that the blocked member caused for the blocker.
     */
    public function forBlock(User $blocker, User $blocked): int;

    /**
     * A chat message was deleted (it stays as a marker): the rows about it.
     */
    public function forChatMessage(int $chatMessageId): int;
}
