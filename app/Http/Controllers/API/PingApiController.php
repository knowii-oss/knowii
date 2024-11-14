<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;

class PingApiController extends Controller
{
    use ApiResponses;

    final public function ping(): JsonResponse
    {
        return self::success('Pong', null, null);
    }
}
