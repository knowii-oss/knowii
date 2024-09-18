<?php

namespace App\Http\Controllers\API;


namespace App\Http\Controllers\API;

use App\Contracts\Users\VerifiesUsernameAvailability;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use \App\Traits\ApiResponses;

class UserApiController extends Controller
{
  use ApiResponses;

  /**
   * Check if the given username is available
   * @param Request $request
   * @return JsonResponse
   */
  final public function isUsernameAvailable(Request $request): JsonResponse
  {
    Log::info('Processing API request to verify the availability of a username.');
    Log::debug("User: ", [$request->user()]);

    $checker = app(VerifiesUsernameAvailability::class);

    Log::info("Input", [$request->all()]);

    $usernameIsAvailable = $checker->verify($request->user(), $request->all());

    return $this->success("Username availability check completed", null, ['isUsernameAvailable' => $usernameIsAvailable]);
  }
}
