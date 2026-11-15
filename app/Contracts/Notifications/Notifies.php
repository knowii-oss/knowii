<?php

namespace App\Contracts\Notifications;

use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Post;

/**
 * Tells the members that a new post, comment or chat message is about (#1474, ADR 0042). Callers never write
 * notification rows: they call notify() after they wrote the item, and the action holds every rule.
 */
interface Notifies
{
    /**
     * Queue the notifications of a new item. It does nothing inside Notify::silenced(), for a direct message, or for
     * an item that is older than 15 minutes. The job runs on the queue `notifications`, after the commit of the caller.
     */
    public function notify(Post|Comment|ChatMessage $item): void;

    /**
     * Write the rows of a new item now. The queued job calls this: callers use notify().
     */
    public function deliver(Post|Comment|ChatMessage $item): void;
}
