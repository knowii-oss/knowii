<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class PingController extends Controller
{
  public function ping(): JsonResponse
  {
    return response()->json(['pong' => true]);
  }
}
