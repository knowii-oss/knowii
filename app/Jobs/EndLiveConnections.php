<?php

namespace App\Jobs;

use App\Constants;
use App\Contracts\Realtime\EndsLiveConnections;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ends the live connections of some accounts after a change of their access (ADR 0075): the pages connect again, and each
 * channel asks its rule again. On the queue `broadcasts`, after the commit: never inside the request of the change. Ending
 * the connections twice does no harm. A failure is one line of the log with the cuids only, and the job is tried again
 * later, TRIES times in all.
 */
class EndLiveConnections implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const int TRIES = 3;

    public const int RETRY_SECONDS = 30;

    public int $tries = self::TRIES;

    /**
     * @param  list<string>  $userCuids  The accounts (User::getAuthIdentifierForBroadcasting())
     */
    public function __construct(public readonly array $userCuids)
    {
        $this->onQueue(Constants::QUEUE_BROADCASTS);
        $this->afterCommit();
    }

    final public function handle(EndsLiveConnections $connections): void
    {
        try {
            foreach ($this->userCuids as $cuid) {
                $connections->endFor($cuid);
            }
        } catch (Throwable $e) {
            $attempt = $this->attempts();
            Log::warning('The WebSocket server did not end the live connections of some accounts', ['users' => $this->userCuids, 'error' => $e::class, 'attempt' => $attempt]);
            if ($attempt < self::TRIES) {
                $this->release(self::RETRY_SECONDS);
            }
        }
    }
}
