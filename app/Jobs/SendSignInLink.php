<?php

namespace App\Jobs;

use App\Actions\Auth\MagicLinks;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The work of the form of the sign-in link, for every address that has the format of an address (ADR 0023,
 * documentation/architecture/backend.md). The request only queues this job, so its answer does the same work for every
 * address. The job finds the account, and sends the link at most once a minute per account (MagicLinks::deliver()). It
 * holds the address and the landing (ADR 0057), so it is encrypted in the queue. It runs on the queue `default`, as the
 * other account mails, with no limiter and no hold.
 *
 * The job always ends as done, also on an error: one log line with the id of the job and the class of the error, never
 * its text, so no log line and no failed job holds the address or the token.
 */
class SendSignInLink implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter] public readonly string $email,
        public readonly ?string $landing = null,
    ) {}

    public function handle(MagicLinks $links): void
    {
        try {
            $links->deliver($this->email, $this->landing);
        } catch (Throwable $exception) {
            Log::warning('A sign-in link job ended with an error', ['job' => $this->jobId(), 'error' => $exception::class]);
        }
    }

    private function jobId(): ?string
    {
        $id = $this->job?->getJobId();

        return is_scalar($id) ? (string) $id : null;
    }
}
