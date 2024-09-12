<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use \App\Traits\ApiResponses;
class LoginApiController extends Controller
{

  use ApiResponses;

  /**
   * Handle API login requests
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function __invoke(Request $request)
  {
    $credentials = $request->validate([
      'email' => ['required', 'email'],
      'password' => ['required'],
    ]);

    if (Auth::attempt($credentials)) {
      $user = Auth::user();
      Log::info('Authenticated successfully: '.$user->email);

      // Initialize the session
      session()->regenerate();

      // If the client has re
      if($request->has("includeToken") && $request->get("includeToken")) {
          Log::info("Including token in response");
          $tokenValidUntil = now()->addHours(12);
          Log::info("Token valid until: ".$tokenValidUntil);
          $token = $user->createToken('api', [], $tokenValidUntil)->plainTextToken;
          
          return response()->json([
              'message' => __('Welcome to Knowii\'s API'),
              'token' => $token,
              'tokenValidUntil' => $tokenValidUntil,
          ], Response::HTTP_OK);
      }

      return $this->success('Welcome to Knowii\'s API');
    }

    return $this->error('The provided credentials do not match our records.', Response::HTTP_UNAUTHORIZED);
  }
}
