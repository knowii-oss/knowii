<?php

namespace App\Listeners;

use App\Support\MailSettings;
use App\Support\MailWorkerActivity;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notes that a worker ended a job of the mail queue (#1477, ADR 0054): sent, held, or released by the rate limiter. The
 * check `mail-queue` of `app:health` reads it: while jobs end, old jobs in the queue are a backlog and not a stopped
 * worker. A send that hangs ends no job, so it does not hide a blocked worker.
 *
 * It never throws: the job already ran, and an exception here would run it again and send the mail twice.
 */
class RecordMailWorkerActivity
{
    final public function handleJobProcessed(JobProcessed $event): void
    {
        try {
            if ($event->job->getQueue() === MailSettings::queue()) {
                MailWorkerActivity::record();
            }
        } catch (Throwable $exception) {
            Log::error('The activity of the mail worker could not be noted', ['exception' => $exception::class]);
        }
    }
}
