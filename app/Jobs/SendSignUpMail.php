<?php

namespace App\Jobs;

use App\Actions\Auth\PendingSignUps;
use App\Mail\FinishSignUp;
use App\Mail\SignUpAccountExists;
use App\Models\User;
use App\Support\AccountMailCaps;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The work of the sign-up form, for every address that passed the checks of the form (ADR 0065). The request only queues
 * this job, so its answer does the same work for every address. The job:
 *
 * 1. Sends at most one mail of the sign-up per address a minute, both kinds together, whatever the IP address: a cache key
 *    with an HMAC of the address, never the address. A second job in that minute sends nothing. An address gets at most
 *    Constants::ACCOUNT_MAILS_PER_ADDRESS_PER_DAY mails of the sign-up a day, both kinds together
 *    (App\Support\AccountMailCaps): a mail over the cap is not sent.
 * 2. An account has the address, without regard to the case (also an account whose address was never verified): the mail
 *    "You already have an account on Knowii" (SignUpAccountExists) to the stored address of the account.
 * 3. No account has the address: a pending sign-up (PendingSignUps) and the mail "Finish your sign-up" (FinishSignUp), with
 *    the link of its token.
 *
 * It holds the address, the name and the landing (ADR 0057), so it is encrypted in the queue. It runs on the queue
 * `default`, as the other account mails, with no limiter and no hold. It always ends as done, also on an error: one log
 * line with the id of the job and the class of the error, never its text, so no log line holds the address or the token.
 */
class SendSignUpMail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * At most one mail of the sign-up per address in this time
     */
    public const int SECONDS_BETWEEN_MAILS = 60;

    public function __construct(
        #[\SensitiveParameter] public readonly string $email,
        #[\SensitiveParameter] public readonly string $name,
        public readonly ?string $landing = null,
    ) {}

    public function handle(): void
    {
        try {
            $address = mb_strtolower(trim($this->email));
            if (! Cache::add(self::sentKey($address), true, self::SECONDS_BETWEEN_MAILS)) {
                Log::info('A sign-up mail is not sent: one was sent to the address less than a minute ago', ['job' => $this->jobId()]);

                return;
            }

            if (! AccountMailCaps::take(AccountMailCaps::SIGN_UP, $address)) {
                Log::info('A sign-up mail is not sent: the address got all its sign-up mails of the day', ['job' => $this->jobId()]);

                return;
            }

            $user = User::query()->whereRaw('lower(email) = ?', [$address])->first();
            if ($user !== null) {
                Mail::to($user->email)->queue((new SignUpAccountExists)->recordDelivery($user));
                Log::info('Sign-up asked for the address of an account: the mail of an account was sent', ['user' => $user->cuid]);

                return;
            }

            $token = PendingSignUps::start($this->name, $address, $this->landing);
            Mail::to($address)->queue((new FinishSignUp($token, PendingSignUps::validityMinutes()))->recordDelivery());
            Log::info('Sign-up asked: the link was sent', ['job' => $this->jobId()]);
        } catch (Throwable $exception) {
            Log::warning('A sign-up job ended with an error', ['job' => $this->jobId(), 'error' => $exception::class]);
        }
    }

    /**
     * The key of the mails of an address, with an HMAC of the address and the key of the application: never the address
     */
    private static function sentKey(string $address): string
    {
        return 'sign-up-mail:'.hash_hmac('sha256', 'sign-up:'.$address, config()->string('app.key'));
    }

    private function jobId(): ?string
    {
        $id = $this->job?->getJobId();

        return is_scalar($id) ? (string) $id : null;
    }
}
