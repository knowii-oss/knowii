<?php

namespace App\Jobs;

use App\Constants;
use App\Contracts\Notifications\Notifies;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One chunk of the level 2 fan-out of a new item (#1475, ADR 0042, design 6.14): the members who follow the thread, who
 * chose "Everything" for the space, or who started the course. Each chunk is its own job on the queue `notifications`: it
 * reads at most `$size` members after the cursor `$after`, writes their rows, and queues the job of the next chunk when
 * this one was full. A retry is harmless (see LevelTwoWriter). The first job of a new post waits 5 minutes (design 6.11),
 * and reads the post again when it runs: an edit changes what the rows show, a deletion gives no row.
 */
class FanOutNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    /**
     * @param  ?list<int>  $excluded  the members who get a level 1 row for the item; null: the first chunk reads them
     */
    public function __construct(
        public readonly Post|Comment|ChatMessage|Page $item,
        public readonly ?array $excluded = null,
        public readonly int $after = 0,
        public readonly int $size = Constants::NOTIFICATIONS_FANOUT_CHUNK,
    ) {
        $this->onQueue(Constants::QUEUE_NOTIFICATIONS);
        $this->afterCommit();
    }

    final public function handle(Notifies $notifies): void
    {
        $notifies->fanOut($this->item, $this->excluded, $this->after, $this->size);
    }
}
