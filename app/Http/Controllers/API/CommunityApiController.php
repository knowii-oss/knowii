<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use \App\Traits\ApiResponses;

class CommunityApiController extends Controller
{
  use ApiResponses;

  public function create(Request $request): JsonResponse {
    Log::info('Processing request to create a new community.');
    Log::info("User: ", [$request->user()]);

    return $this->success('Community created successfully', 201);
  }
}
