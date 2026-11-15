<?php

namespace App\Jobs;

use App\Constants;
use App\Contracts\Notifications\Notifies;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Writes the notification rows of a new post, comment or chat message (#1474, ADR 0042). It runs on the queue
 * `notifications`, after the database commit of the action that wrote the item, so the item exists when it runs.
 * An item that was deleted in the meantime gives no notification.
 */
class WriteNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Post|Comment|ChatMessage $item)
    {
        $this->onQueue(Constants::QUEUE_NOTIFICATIONS);
        $this->afterCommit();
    }

    final public function handle(Notifies $notifies): void
    {
        $notifies->deliver($this->item);
    }
}
