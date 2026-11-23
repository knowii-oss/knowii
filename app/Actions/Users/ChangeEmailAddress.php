<?php

namespace App\Actions\Users;

use App\Contracts\Users\ChangesEmailAddresses;
use App\Enums\KnowiiEmailChangeResult;
use App\Mail\ConfirmNewEmailAddress;
use App\Mail\EmailAddressChanged;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A change of the email address applies after the new address is confirmed (#1706, ADR 0058).
 *
 * 1. The profile form (UpdateUserProfileInformation) validates the new address and the password, and calls request(): the
 *    address waits in users.pending_email with the time of the request, and the link goes to it. The account keeps its
 *    current, verified address until the link is opened.
 * 2. The link is a signed, temporary route (config auth.verification.expire, as the address check) with the cuid of the
 *    account and an HMAC-SHA256 of the pending address with the key of the application: the link of a replaced or
 *    cancelled address does not match. It needs a session of the account.
 * 3. confirm() applies it in one transaction, on the row of the account read again and locked, then a notice goes to the
 *    address of before, with the new address masked.
 * 4. A pending address waits Retention::pendingEmailDays() days at most (retention.pending_email_days): after that it does
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

        $this->sendLink($user);
        // After the commit of the caller, as the mail: the line says what is stored
        $cuid = $user->cuid;
        DB::afterCommit(static fn () => Log::info('Change of the email address asked', ['user' => $cuid]));
    }

    final public function resend(User $user): User
    {
        if (self::pendingAddressOf($user) === null) {
            throw ValidationException::withMessages(['pending_email' => __('No change of the email address waits for its confirmation.')]);
        }

        $this->sendLink($user);
        Log::info('Link of the change of the email address sent again', ['user' => $user->cuid]);

        return $user;
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

                $previous = $account->email;
                $account->forceFill([
                    'email' => $pending,
                    'email_verified_at' => now(),
                    'pending_email' => null,
                    'pending_email_requested_at' => null,
                ])->save();
                // The profile keeps a copy of the address
                $account->profile()->update(['email' => $account->email]);
                // A reset link of the address of before has no use any more
                DB::table('password_reset_tokens')->where('email', $previous)->delete();

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

    private function sendLink(User $user): void
    {
        $address = self::pendingAddressOf($user);
        if ($address === null) {
            return;
        }

        $minutes = Config::get('auth.verification.expire', 60);
        $minutes = is_int($minutes) && $minutes > 0 ? $minutes : 60;
        $url = URL::temporarySignedRoute('email-change.confirm', Carbon::now()->addMinutes($minutes), [
            'userCuid' => $user->cuid,
            'hash' => self::addressHash($address),
        ]);

        // After the commit of the caller: the link works when the mail is sent
        DB::afterCommit(static fn () => Mail::to($address)->queue((new ConfirmNewEmailAddress($url, $minutes))->recordDelivery($user)));
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
