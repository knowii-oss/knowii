<?php

namespace App\Actions\Fortify;

use App\Contracts\Auth\TakesOverUnverifiedAccounts;
use App\Enums\KnowiiAddressProof;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly TakesOverUnverifiedAccounts $takesOverAccounts) {}

    /**
     * Validate and reset the user's forgotten password. The broker checked the token of the reset mail before this call.
     *
     * For an account whose address was never verified, the reset mail is the first proof of the address, and the first
     * proof takes the account over (ADR 0059): the address is verified, the account keeps the new password only, and the
     * second factor, the sessions and the tokens of before go. Every other account gets the new password, and nothing else
     * changes: also an account whose address another proof verified a moment before (the takeover then changes nothing). An
     * account whose address changed after the broker checked the token gets the error of a token that does not work, and
     * nothing changes.
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
            if ($this->takesOverAccounts->takeOver($user, KnowiiAddressProof::PasswordReset, $provedAddress, $input['password'])) {
                return;
            }
            // The address was corrected after the broker checked the token: the reset proves nothing about the account, and
            // the answer is the one of a token that does not work
            if (! $user->hasAddress($provedAddress)) {
                throw ValidationException::withMessages(['email' => [__(Password::INVALID_TOKEN)]]);
            }
        }

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
