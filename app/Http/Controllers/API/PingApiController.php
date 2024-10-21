<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

use \App\Traits\ApiResponses;

class PingApiController extends Controller
{
  use ApiResponses;

  final public function ping(): JsonResponse
  {
    return self::success("Pong", null, null);
  }
}
