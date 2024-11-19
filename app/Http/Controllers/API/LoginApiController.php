<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\OpenApi\Parameters\LoginApiRequestParameters;
use App\OpenApi\Requests\LoginApiRequestBody;
use App\OpenApi\Responses\LoginApiResponse;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Vyuldashev\LaravelOpenApi\Attributes as OpenApi;

#[OpenApi\PathItem]
class LoginApiController extends Controller
{
    use ApiResponses;

    /**
     * Log in through the API
     */
    #[OpenApi\Operation(tags: ['auth'], method: 'post')]
    #[OpenApi\Parameters(factory: LoginApiRequestParameters::class)]
    #[OpenApi\RequestBody(factory: LoginApiRequestBody::class)]
    #[OpenApi\Response(factory: LoginApiResponse::class)]
    final public function login(Request $request): JsonResponse
    {
        /** @var array{email: string, password: string} $credentials */
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            if (! $user) {
                return self::authenticationIssue('We could not authenticate your credentials. Please try again later.');
            }

            Log::info('Authenticated successfully: '.$user->email);

            // Initialize the session
            session()->regenerate();

            // If the client has re
            if ($request->has('includeToken') && $request->get('includeToken')) {
                Log::info('Including token in response');
                $tokenValidUntil = now()->addHours(12);
                Log::info('Token valid until: '.$tokenValidUntil);
                $token = $user->createToken('api', [], $tokenValidUntil)->plainTextToken;

                return self::success("Welcome to Knowii's API 🎉", null, [
                    'token' => $token,
                    'tokenValidUntil' => $tokenValidUntil,
                ]);
            }

            return self::success("Welcome to Knowii's API 🎉", null, null);
        }

        return self::authenticationIssue('The provided credentials do not match our records.');
    }
}
