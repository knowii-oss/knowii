<?php

namespace App\Jobs;

use App\Constants;
use App\Contracts\Notifications\PrunesNotifications;
use App\Models\Community;
use App\Models\Space;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deletes the notification rows of the "groups" spaces of a community whose recipient cannot view the space any more
 * (#1474, ADR 0042, design 6.8.4): started when the access groups or the members change, and when a space changes its
 * visibility, its space group or its groups. It runs on the queue `notifications`, after the commit.
 */
class PruneUnviewableNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Community $community, public readonly ?Space $space = null)
    {
        $this->onQueue(Constants::QUEUE_NOTIFICATIONS);
        $this->afterCommit();
    }

    final public function handle(PrunesNotifications $prunes): void
    {
        $prunes->forAccess($this->community, $this->space);
    }
}
