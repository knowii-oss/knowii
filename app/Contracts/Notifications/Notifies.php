<?php

namespace App\Contracts\Notifications;

use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Page;
use App\Models\Post;

/**
 * Tells the members that a new post, comment, chat message, lesson or event is about (#1474, #1475, ADR 0042). Callers never
 * write notification rows: they call notify() after they wrote the item, and the action holds every rule.
 */
interface Notifies
{
    /**
     * Queue the notifications of a new item: the level 1 rows (a reply, a mention) at once, and the level 2 rows
     * ("Following") by chunks. The fan-out of a new post waits 5 minutes (design 6.11) unless `$now` says to send it at
     * once. It does nothing inside Notify::silenced(), for a direct message, or for an item that is older than 15 minutes
     * (an event has no such guard: a draft can be old, and its publication is news; the action calls this once, at the
     * first publication, #1513).
     * The jobs run on the queue `notifications`, after the commit of the caller.
     */
    public function notify(Post|Comment|ChatMessage|Page|Event $item, bool $now = false): void;

    /**
     * Write the level 1 rows of a new item now. The queued job calls this: callers use notify().
     */
    public function deliver(Post|Comment|ChatMessage $item): void;

    /**
     * Write the level 2 rows of one chunk of recipients now, and queue the next chunk when this one was full. The queued
     * job calls this: callers use notify().
     *
     * @param  ?list<int>  $excluded  the members who get a level 1 row for the item; null: read them now
     * @param  int  $after  the id of the last member of the chunk before
     * @param  ?int  $size  the members of a chunk (default 500)
     */
    public function fanOut(Post|Comment|ChatMessage|Page|Event $item, ?array $excluded = null, int $after = 0, ?int $size = null): void;
}
