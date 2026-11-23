<?php

namespace App\Jobs\Middleware;

use Closure;
use RuntimeException;
use Throwable;

/**
 * A queued mail that fails keeps no text of the error: the error of a mail provider can name the recipient, and the
 * worker writes the text of the error to the log and to `failed_jobs.exception`. The middleware throws again an error
 * that names only the class of the error, with no previous error, so the retries and the failure of the job stay as
 * they were.
 */
final class HideMailErrorText
{
    public function handle(object $job, Closure $next): void
    {
        try {
            $next($job);
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf('The mail could not be sent (%s)', $exception::class));
        }
    }
}
