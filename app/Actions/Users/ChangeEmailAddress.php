<?php

namespace App\Actions\Users;

use App\Constants;
use App\Contracts\Users\ChangesEmailAddresses;
use App\Enums\KnowiiEmailChangeResult;
use App\Jobs\SendEmailChangeMail;
use App\Mail\ConfirmNewEmailAddress;
use App\Mail\EmailAddressChanged;
use App\Mail\EmailAddressHasAccount;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A change of the email address applies after the new address is confirmed (#1706, ADR 0058).
 *
 * 1. The profile form (UpdateUserProfileInformation) validates the new address and the password, and calls request(): the
 *    address waits in users.pending_email with the time of the request, and one job (App\Jobs\SendEmailChangeMail) is
 *    queued. The account keeps its current address until the link is opened. The request does the same work for a free
 *    address and for an address that another account has: it never reads another account.
 * 2. The job (deliver()) sends the link, or, when another account has the address, the mail "This address already has an
 *    account" in place of the link (at most one a minute and Constants::EMAIL_ADDRESS_HAS_ACCOUNT_MAILS_PER_HOUR an hour
 *    per recipient). The pending address stays then: a cancel, a newer change, a takeover or the retention removes it.
 * 3. The link is a signed, temporary route (config auth.verification.expire, as the address check) with the cuid of the
 *    account and an HMAC-SHA256 of the pending address with the key of the application: the link of a replaced or
 *    cancelled address does not match. It needs a session of the account.
 * 4. confirm() applies it in one transaction, on the row of the account read again and locked, then a notice goes to the
 *    address of before when it was verified.
 * 5. A pending address waits Retention::pendingEmailDays() days at most (retention.pending_email_days): after that it does
 *    not count, and retention:prune removes it.
 *
 * The mails are account mails of level 0 (ADR 0054): on the default queue, never held. The log lines hold the cuid of the
 * account, never an address.
 */
class ChangeEmailAddress implements ChangesEmailAddresses
{
    final public function request(User $user, string $address): void
    {
        $user->forceFill([
            'pending_email' => $address,
            'pending_email_requested_at' => now(),
        ])->save();

        $this->queueMail($user);
        // After the commit of the caller, as the mail: the line says what is stored
        $cuid = $user->cuid;
        DB::afterCommit(static fn () => Log::info('Change of the email address asked', ['user' => $cuid]));
    }

    final public function resend(User $user): User
    {
        if (self::pendingAddressOf($user) === null) {
            throw ValidationException::withMessages(['pending_email' => __('No change of the email address waits for its confirmation.')]);
        }

        $this->queueMail($user);
        Log::info('Link of the change of the email address sent again', ['user' => $user->cuid]);

        return $user;
    }

    final public function deliver(int $userId, string $addressHash): void
    {
        $user = User::query()->find($userId);
        $address = $user === null ? null : self::pendingAddressOf($user);
        // A newer change, a cancel, a confirmation or a takeover came after the request: nothing to send
        if ($user === null || $address === null || ! hash_equals(self::addressHash($address), $addressHash)) {
            return;
        }

        $taken = User::query()
            ->whereKeyNot($user->id)
            ->whereRaw('lower(email) = ?', [mb_strtolower($address)])
            ->exists();
        if ($taken) {
            // At most one such mail a minute and EMAIL_ADDRESS_HAS_ACCOUNT_MAILS_PER_HOUR an hour per recipient, whatever the
            // account that asks: a mail over the cap is dropped, and the answer of the request does not change
            $key = self::recipientKey($address);
            if (! Cache::add($key.':minute', true, 60) || RateLimiter::tooManyAttempts($key.':hour', Constants::EMAIL_ADDRESS_HAS_ACCOUNT_MAILS_PER_HOUR)) {
                Log::info('Change of the email address: the new address has an account, and its mail without a link is over the cap', ['user' => $user->cuid]);

                return;
            }
            RateLimiter::hit($key.':hour', 3600);

            // No link: the account keeps its address, and the pending address stays (clearing it would show on the profile)
            Mail::to($address)->queue((new EmailAddressHasAccount)->recordDelivery($user));
            Log::info('Change of the email address: the new address has an account, the mail without a link was sent', ['user' => $user->cuid]);

            return;
        }

        $minutes = Config::get('auth.verification.expire', 60);
        $minutes = is_int($minutes) && $minutes > 0 ? $minutes : 60;
        $url = URL::temporarySignedRoute('email-change.confirm', Carbon::now()->addMinutes($minutes), [
            'userCuid' => $user->cuid,
            'hash' => $addressHash,
        ]);

        Mail::to($address)->queue((new ConfirmNewEmailAddress($url, $minutes))->recordDelivery($user));
    }

    final public function cancel(User $user): User
    {
        if ($user->getRawOriginal('pending_email') !== null || $user->getRawOriginal('pending_email_requested_at') !== null) {
            $user->forceFill(['pending_email' => null, 'pending_email_requested_at' => null])->save();
            Log::info('Change of the email address cancelled', ['user' => $user->cuid]);
        }

        return $user;
    }

    final public function confirm(User $user, string $addressHash): KnowiiEmailChangeResult
    {
        try {
            /** @var array{0: KnowiiEmailChangeResult, 1: string|null} $outcome */
            $outcome = DB::transaction(static function () use ($user, $addressHash): array {
                // The row as it is stored now, locked until the commit
                $account = User::query()->lockForUpdate()->findOrFail($user->id);
                $pending = self::pendingAddressOf($account);
                if ($pending === null || ! hash_equals(self::addressHash($pending), $addressHash)) {
                    return [KnowiiEmailChangeResult::NotPending, null];
                }

                $taken = User::query()
                    ->whereKeyNot($account->id)
                    ->whereRaw('lower(email) = ?', [mb_strtolower($pending)])
                    ->exists();
                if ($taken) {
                    $account->forceFill(['pending_email' => null, 'pending_email_requested_at' => null])->save();

                    return [KnowiiEmailChangeResult::AddressTaken, null];
                }

                // The notice goes only to an address that was verified: the correction of an address that was never
                // verified sends none, as before (ADR 0058)
                $previous = $account->hasVerifiedEmail() ? $account->email : null;
                $oldAddress = $account->email;
                $account->forceFill([
                    'email' => $pending,
                    'email_verified_at' => now(),
                    'pending_email' => null,
                    'pending_email_requested_at' => null,
                ])->save();
                // The profile keeps a copy of the address
                $account->profile()->update(['email' => $account->email]);
                // A reset link of the address of before has no use any more
                DB::table('password_reset_tokens')->where('email', $oldAddress)->delete();

                return [KnowiiEmailChangeResult::Confirmed, $previous];
            });
        } catch (UniqueConstraintViolationException) {
            // Another account took the address between the check and the write
            $user->forceFill(['pending_email' => null, 'pending_email_requested_at' => null])->save();
            $outcome = [KnowiiEmailChangeResult::AddressTaken, null];
        }

        // The caller goes on with the stored values
        $user->refresh();

        [$result, $previous] = $outcome;
        if ($result === KnowiiEmailChangeResult::Confirmed && $previous !== null) {
            // The address of before gets a notice: the person who reads it learns of the change
            Mail::to($previous)->queue((new EmailAddressChanged(self::masked($user->email)))->recordDelivery($user));
        }
        Log::info('Change of the email address: '.$result->value, ['user' => $user->cuid]);

        return $result;
    }

    /**
     * The pending address of the account, or null: none, or it waited for more than Retention::pendingEmailDays() days
     * (User::pendingEmail())
     */
    private static function pendingAddressOf(User $user): ?string
    {
        return $user->pending_email;
    }

    /**
     * One job for the pending address, after the commit of the caller: the job reads the row as it is stored
     */
    private function queueMail(User $user): void
    {
        $address = self::pendingAddressOf($user);
        if ($address === null) {
            return;
        }

        $userId = $user->id;
        $hash = self::addressHash($address);
        DB::afterCommit(static fn () => SendEmailChangeMail::dispatch($userId, $hash));
    }

    /**
     * The key of the mails without a link to one recipient: an HMAC of the address with the key of the application, never
     * the address
     */
    private static function recipientKey(string $address): string
    {
        return 'email-change-taken:'.hash_hmac('sha256', 'email-change-taken:'.mb_strtolower($address), config()->string('app.key'));
    }

    /**
     * The pending address as the link holds it: an HMAC-SHA256 with the key of the application, so that the link tells
     * nothing about the address, and a link of another address never matches
     */
    private static function addressHash(string $address): string
    {
        return hash_hmac('sha256', 'pending-email:'.$address, config()->string('app.key'));
    }

    /**
     * The address with most of the name hidden: "a***@example.com"
     */
    private static function masked(string $address): string
    {
        $name = Str::before($address, '@');
        $domain = Str::after($address, '@');

        return mb_substr($name, 0, 1).'***@'.$domain;
    }
}
