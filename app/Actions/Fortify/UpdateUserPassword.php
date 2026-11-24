<?php

namespace App\Actions\Fortify;

use App\Contracts\Auth\EndsOtherSessions;
use App\Models\User;
use App\Support\PasswordTries;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly EndsOtherSessions $endsOtherSessions) {}

    /**
     * Validate and update the user's password.
     *
     * An account that never verified an address cannot change its password (ADR 0059): the form gets the error of a wrong
     * current password. Its address is verified first (the link of the address check), or a password reset proves it.
     *
     * The current password is checked with the tries of the session (App\Support\PasswordTries): a wrong one counts,
     * whatever the other fields hold, and the fifth wrong one in 15 minutes ends the session.
     *
     * A change of the password signs out every other session of the account, on every route (ADR 0066): the version of the
     * sessions goes up by 1 in the write of the new password, and the session of this request goes on, with a new session
     * id. When the account changed elsewhere while the request ran, nothing is written and the session of the request ends.
     *
     * @param  array<string, string>  $input
     *
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $validator = Validator::make($input, [
            'current_password' => ['required', 'string', static function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                if (! PasswordTries::check($user, static fn (): bool => is_string($value) && Hash::check($value, (string) $user->password))) {
                    $fail(__('The provided password does not match your current password.'));
                }
            }],
            'password' => $this->passwordRules(),
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

        $this->endsOtherSessions->endFor($user, ['password' => Hash::make($input['password'])]);
    }
}
