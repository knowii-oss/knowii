<?php

namespace App\Http\Controllers\API;

namespace App\Http\Controllers\API;

use App\Contracts\Users\UpdatesAvatars;
use App\Contracts\Users\UpdatesOnlineStatus;
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
     * Replace the avatar of the current user (#109): a JPEG, PNG, WebP or GIF image of 10 MB at most, re-encoded and
     * cropped to a square. Returns the current user with avatar_url.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('file', description: 'The image (multipart/form-data)', required: true, type: 'string', format: 'binary')]
    final public function updateAvatar(Request $request): JsonResponse
    {
        $user = app(UpdatesAvatars::class)->update($request->user() ?? throw new AuthenticationException, $request->only(['file']));

        return self::success('Avatar updated', null, $user->refresh()->load('profile')->toResource());
    }

    /**
     * Show or hide when the current user is online (#1187, ADR 0027). Hidden, the member never appears in the online
     * lists, and still sees the other members there. Returns the current user.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('show_online_status', description: 'true: the other members see when you are online. false: you never appear online', required: true, type: 'boolean', example: false)]
    final public function updateOnlineStatus(Request $request): JsonResponse
    {
        $user = app(UpdatesOnlineStatus::class)->update($request->user() ?? throw new AuthenticationException, $request->only(['show_online_status']));

        return self::success('Online status updated', null, $user->refresh()->load('profile')->toResource());
    }

    /**
     * Remove the avatar of the current user: the initials show again.
     *
     * @throws AuthenticationException
     */
    final public function deleteAvatar(Request $request): JsonResponse
    {
        $user = app(UpdatesAvatars::class)->delete($request->user() ?? throw new AuthenticationException);

        return self::success('Avatar removed', null, $user->refresh()->load('profile')->toResource());
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
