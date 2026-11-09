<?php

namespace App\Http\Controllers\API;

namespace App\Http\Controllers\API;

use App\Contracts\Users\VerifiesUsernameAvailability;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserApiController extends Controller
{
    use ApiResponses;

    /**
     * The signed-in user: account fields (email, verification, two-factor status) and profile.
     */
    final public function me(Request $request): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        // refresh(): every column, also the ones a model built in memory does not hold (two_factor_confirmed_at)
        return self::success('Current user', null, $user->refresh()->load('profile')->toResource());
    }

    /**
     * Check if the given username is available
     */
    #[BodyParameter('usernameToCheck', description: 'Username to check (3 to 36 characters)', required: true, type: 'string', example: 'ada-lovelace')]
    final public function isUsernameAvailable(Request $request): JsonResponse
    {
        Log::info('Processing API request to verify the availability of a username.');
        Log::debug('User: ', [$request->user()]);

        $checker = app(VerifiesUsernameAvailability::class);

        Log::info('Input', [$request->all()]);

        $usernameIsAvailable = false;
        $user = $request->user();

        if ($user) {
            $usernameIsAvailable = $checker->verify($user, $request->all());
        }

        return self::success('Username availability check completed', null, ['isUsernameAvailable' => $usernameIsAvailable]);
    }
}
