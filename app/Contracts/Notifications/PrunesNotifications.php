<?php

namespace App\Contracts\Notifications;

use App\Models\ChatMessage;
use App\Models\Community;
use App\Models\Event;
use App\Models\Post;
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

    /**
     * Posts, comments or chat messages are deleted or become markers (#1344): the rows about them, with one event per
     * recipient. The database cascades only cover the rows that really go.
     *
     * @param  list<int>  $postIds
     */
    public function forPosts(array $postIds): int;

    /**
     * @param  list<int>  $commentIds
     */
    public function forComments(array $commentIds): int;

    /**
     * @param  list<int>  $chatMessageIds
     */
    public function forChatMessages(array $chatMessageIds): int;

    /**
     * An event went back to draft (#1513): its "new event" lines go, for every member. A draft is for its host, the
     * owner and the admins.
     */
    public function forDraftEvent(Event $event): int;

    /**
     * The event has another host (#1516): the rows "commented on your event" of the former host for this event go. They
     * say "You host this event", which is not true any more, and their count would grow with the comments that the new
     * host is told about.
     */
    public function forFormerHost(Event $event, int $formerHostId): int;

    /**
     * The member muted the space (#1475): their open rows of that space go, at every level. The next ones are never written.
     */
    public function forMutedSpace(User $member, Space $space): int;

    /**
     * The member muted a thread (#1475): their open rows about the thread go, at every level: the rows about the post and
     * its comments, or about the chat message and its replies.
     */
    public function forMutedThread(User $member, Post|ChatMessage $thread): int;
}
