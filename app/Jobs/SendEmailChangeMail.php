<?php

namespace App\Jobs;

use App\Contracts\Users\ChangesEmailAddresses;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The mail of a change of the email address (ADR 0058), for every new address: the profile form and "Send the link again"
 * only queue this job, so their answer does the same work for a free address and for an address that another account has.
 * The job holds the id of the account and an HMAC of the pending address, never the address, and reads the row of the
 * account again (ChangesEmailAddresses::deliver()). It is encrypted in the queue, as the other jobs of an address, and runs
 * on the queue `default`, with no limiter and no hold.
 *
 * The job always ends as done, also on an error: one log line with the id of the job and the class of the error, never its
 * text, so no log line holds the address.
 */
class SendEmailChangeMail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $userId,
        #[\SensitiveParameter] public readonly string $addressHash,
    ) {}

    public function handle(ChangesEmailAddresses $changes): void
    {
        try {
            $changes->deliver($this->userId, $this->addressHash);
        } catch (Throwable $exception) {
            Log::warning('An email change job ended with an error', ['job' => $this->jobId(), 'error' => $exception::class]);
        }
    }

    private function jobId(): ?string
    {
        $id = $this->job?->getJobId();

        return is_scalar($id) ? (string) $id : null;
    }
}
