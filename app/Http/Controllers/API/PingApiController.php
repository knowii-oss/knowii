<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PingApiController extends Controller
{
  public function ping(): JsonResponse
  {
    return response()->json(['pong' => true]);
  }
}
