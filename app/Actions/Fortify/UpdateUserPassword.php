<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * An account that never verified an address cannot change its password (ADR 0059): the form gets the error of a wrong
     * current password. Its address is verified first (the link of the address check), or a password reset proves it.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $validator = Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ]);

        // The model of the request decides (ADR 0059)
        $validator->after(static function (ValidatorContract $validator) use ($user): void {
            if ($user->addressWasNeverVerified() && ! $validator->errors()->has('current_password')) {
                $validator->errors()->add('current_password', __('The provided password does not match your current password.'));
            }
        });

        $validator->validateWithBag('updatePassword');

        /** @var array{current_password: string, password: string} $input */
        $input = $validator->validated();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
