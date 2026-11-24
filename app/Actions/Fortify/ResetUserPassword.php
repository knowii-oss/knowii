<?php

namespace App\Actions\Fortify;

use App\Contracts\Auth\EndsOtherSessions;
use App\Contracts\Auth\TakesOverUnverifiedAccounts;
use App\Enums\KnowiiAddressProof;
use App\Enums\KnowiiTakeOverResult;
use App\Exceptions\AccountChangedException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(
        private readonly TakesOverUnverifiedAccounts $takesOverAccounts,
        private readonly EndsOtherSessions $endsOtherSessions,
    ) {}

    /**
     * Validate and reset the user's forgotten password. The broker checked the token of the reset mail before this call.
     *
     * For an account whose address was never verified, the reset mail is the first proof of the address, and the first
     * proof takes the account over (ADR 0059): the address is verified, the account keeps the new password only, and the
     * second factor, the sessions and the tokens of before go. Every other account gets the new password, and every session
     * of the account ends, on every route (ADR 0066): the version of the sessions goes up by 1 in the write of the new
     * password. Nothing else changes: also for an account whose address another proof verified a moment before (the takeover
     * then changes nothing). An account whose address changed, or that was deleted, after the broker checked the token gets
     * the error of a token that does not work, and nothing changes. An account that another change wrote to while the
     * request ran gets "Your account changed during this request", nothing changes, and the link of the mail still works.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        $validator = Validator::make($input, [
            'password' => $this->passwordRules(),
        ]);

        $validator->validate();

        /** @var array{password: string} $input */
        $input = $validator->validated();

        if ($user->addressWasNeverVerified()) {
            // The broker found the account by this address, and checked the token of the reset mail for it
            $provedAddress = $user->email;
            $result = $this->takesOverAccounts->takeOver($user, KnowiiAddressProof::PasswordReset, $provedAddress, $input['password']);
            if ($result === KnowiiTakeOverResult::TookOver) {
                return;
            }
            // The address was corrected, or the account was deleted, after the broker checked the token: the reset proves
            // nothing about the account, and the answer is the one of a token that does not work
            if (! $result->provesTheAccount()) {
                throw ValidationException::withMessages(['email' => [__(Password::INVALID_TOKEN)]]);
            }
        }

        // The reset has no session of the account to keep: the person signs in with the new password
        try {
            $this->endsOtherSessions->endFor($user, ['password' => Hash::make($input['password'])]);
        } catch (AccountChangedException $exception) {
            // The account changed elsewhere while the request ran: nothing is written, the form says so, and the link of the
            // mail still works
            throw ValidationException::withMessages(['email' => [__($exception->getMessage())]]);
        }
    }
}
