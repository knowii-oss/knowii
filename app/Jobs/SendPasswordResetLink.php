<?php

namespace App\Jobs;

use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Throwable;

/**
 * The work of the password reset form, for every address that has the format of an address (documentation/architecture/
 * backend.md): the steps of the broker of the framework, without its timebox. The request only queues this job, so its
 * answer does the same work for every address, and nobody waits for the job: a job for an address without an account is
 * one query. The job finds the account, keeps the throttle of the broker (a link a minute), makes the token and queues
 * the reset mail (`App\Notifications\ResetPasswordLink`). It holds the address, so it is encrypted in the queue. It runs
 * on the queue `default`, as the other account mails, with no limiter and no hold.
 *
 * The job always ends as done: every case ends it, and so does an error. Two jobs for the same address at the same
 * moment both make a token, and the second one finds the row of the first one (a unique violation): the first one sends
 * the mail. No log line and no failed job holds the address or the token: an error writes one line with the id of the
 * job and the class of the error, never its text.
 */
class SendPasswordResetLink implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] public readonly string $email) {}

    public function handle(): void
    {
        try {
            $broker = Password::broker(config()->string('fortify.passwords', 'users'));
            if (! $broker instanceof PasswordBroker) {
                throw new RuntimeException('The password broker is not the broker of the framework');
            }

            $user = $broker->getUser(['email' => $this->email]);
            $tokens = $broker->getRepository();
            if ($user === null || $tokens->recentlyCreatedToken($user)) {
                return;
            }

            // One transaction: the delete and the insert of the token and the queued mail go together, and a refused
            // insert leaves no broken transaction behind
            DB::transaction(static function () use ($user, $tokens): void {
                $user->sendPasswordResetNotification($tokens->create($user));
            });
            event(new PasswordResetLinkSent($user));
        } catch (UniqueConstraintViolationException) {
            Log::info('A password reset link was asked for twice at the same moment: the first one is sent', ['job' => $this->jobId()]);
        } catch (Throwable $exception) {
            Log::warning('A password reset job ended with an error', ['job' => $this->jobId(), 'error' => $exception::class]);
        }
    }

    private function jobId(): ?string
    {
        $id = $this->job?->getJobId();

        return is_scalar($id) ? (string) $id : null;
    }
}
