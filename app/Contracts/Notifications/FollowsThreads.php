<?php

namespace App\Contracts\Notifications;

use App\Enums\KnowiiFollowReason;
use App\Models\ChatMessage;
use App\Models\NotificationFollow;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The follows of a member (#1475, ADR 0042, design 2.2): a thread is a post with its comments, or a first-level chat
 * message with its replies. A member who writes in a thread follows it, unless they stopped following it by hand or muted
 * it. A mention does not make them follow.
 */
interface FollowsThreads
{
    /**
     * The row of the member for the thread, or null when they have none (they do not follow it). Only a member who can
     * view the space reads it.
     *
     * @throws AuthorizationException
     */
    public function state(User $user, Post|ChatMessage $thread): ?NotificationFollow;

    /**
     * "Follow": the member is told about the new comments or replies of the thread. A thread that they already follow
     * keeps its reason.
     *
     * @throws AuthorizationException
     */
    public function follow(User $user, Post|ChatMessage $thread): NotificationFollow;

    /**
     * "Stop following": the row stays with the state "stopped", so that writing in the thread again does not follow it
     * again without a new request.
     *
     * @throws AuthorizationException
     */
    public function stopFollowing(User $user, Post|ChatMessage $thread): NotificationFollow;

    /**
     * "Mute this thread": nothing reaches the member from the thread, a mention included. The open rows about the thread
     * go.
     *
     * @throws AuthorizationException
     */
    public function mute(User $user, Post|ChatMessage $thread): NotificationFollow;

    /**
     * "Undo" of a mute (#1509): the row is back to what it held before the first mute. A thread that had no row has no row
     * again (null), a thread that was followed is followed again with its earlier reason, a thread that was stopped is
     * stopped again. The notifications that the mute deleted do not come back. A thread that is not muted does not change:
     * its current row (or null) is returned.
     *
     * @throws AuthorizationException
     */
    public function unmute(User $user, Post|ChatMessage $thread): ?NotificationFollow;

    /**
     * The member wrote in the thread: they follow it, unless they have a row already (following, stopped or muted). It
     * does nothing inside Notify::silenced(), for a member who left the community, or for a marker.
     */
    public function followByWriting(User $user, Post|ChatMessage $thread, KnowiiFollowReason $reason): void;
}
