<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

use \App\Traits\ApiResponses;

class PingApiController extends Controller
{
  use ApiResponses;

  public function ping(): JsonResponse
  {
    return $this->success('Pong');
  }
}
