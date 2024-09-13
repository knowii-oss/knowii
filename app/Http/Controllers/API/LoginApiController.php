<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\OpenApi\Parameters\LoginApiRequestParameters;
use App\OpenApi\Requests\LoginApiRequestBody;
use App\OpenApi\Responses\LoginApiResponse;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Vyuldashev\LaravelOpenApi\Attributes as OpenApi;

#[OpenApi\PathItem]
class LoginApiController extends Controller
{

  use ApiResponses;

  /**
   * Log in through the API
   * @param Request $request
   * @return JsonResponse
   */
  #[OpenApi\Operation(tags: ['auth'], method: 'post')]
  #[OpenApi\Parameters(factory: LoginApiRequestParameters::class)]
  #[OpenApi\RequestBody(factory: LoginApiRequestBody::class)]
  #[OpenApi\Response(factory: LoginApiResponse::class)]
  final public function login(Request $request): JsonResponse
  {
    $credentials = $request->validate([
      'email' => ['required', 'email'],
      'password' => ['required'],
    ]);

    if (Auth::attempt($credentials)) {
      $user = Auth::user();

      if (!$user) {
        return $this->error('We could not authenticate your credentials. Please try again later.', Response::HTTP_UNAUTHORIZED);
      }

      Log::info('Authenticated successfully: ' . $user->email);

      // Initialize the session
      session()->regenerate();

      // If the client has re
      if ($request->has("includeToken") && $request->get("includeToken")) {
        Log::info("Including token in response");
        $tokenValidUntil = now()->addHours(12);
        Log::info("Token valid until: " . $tokenValidUntil);
        $token = $user->createToken('api', [], $tokenValidUntil)->plainTextToken;

        return $this->successWithData('Welcome to Knowii\'s API', [
          'token' => $token,
          'tokenValidUntil' => $tokenValidUntil,
        ]);
      }

      return $this->success('Welcome to Knowii\'s API');
    }

    return $this->error('The provided credentials do not match our records.', Response::HTTP_UNAUTHORIZED);
  }
}
