<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoginApiController extends Controller
{
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
          ],200);
      }


      return response()->json([
          'message' => __('Welcome to Knowii\'s API'),
      ],200);
    }

    return response()->json([
      'message' => 'The provided credentials do not match our records.'
    ], 401);
  }
}
