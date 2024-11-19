<?php

namespace App\Actions\Users;

use App\Constants;
use App\Contracts\Users\VerifiesUsernameAvailability;
use App\Exceptions\TechnicalException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VerifyUsernameAvailability implements VerifiesUsernameAvailability
{
    /**
     * Validate and create a new community for the given user.
     *
     * @return bool true if available
     *
     * @throws TechnicalException|ValidationException
     */
    final public function verify(User $user, array $input): bool
    {
        Log::info('Verifying the availability of a username');

        Log::debug('Validating the input');

        $validator = Validator::make($input, [
            // WARNING: Those rules must remain aligned with those in UpdateUser>ProfileInformation.php and with the client-side usernameSchema
            'usernameToCheck' => ['required', 'string', 'min:'.Constants::$MIN_LENGTH_USER_USERNAME, 'max:'.Constants::$MAX_LENGTH_USER_USERNAME, 'regex:'.Constants::$ALLOWED_USER_USERNAME_CHARACTERS_REGEX],
        ]);

        $validator->validate();

        /** @var array{usernameToCheck: string} $input */
        $input = $validator->validated();

        Log::debug('Input validated');

        // At this point business validations are done, so all that can happen is a technical issue
        $usernameToCheck = strtolower($input['usernameToCheck']);

        Log::debug('Checking the availability of the following username: ', [$usernameToCheck]);

        $retVal = true; // consider it is available by default

        try {
            $usagesOfThatUsername = DB::table('users')->whereLike('username', $usernameToCheck, caseSensitive: false)->count();
            if ($usagesOfThatUsername > 0) {
                Log::debug('Username is not available');
                $retVal = false;
            }

            return $retVal;
        } catch (\Exception $e) {
            Log::warning('Failed to check the username availability', ['exception' => $e]);
            throw new TechnicalException('Failed to check the username availability', null, $e);
        }
    }
}
