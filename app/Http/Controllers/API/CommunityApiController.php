<?php

namespace App\Http\Controllers\API;

use App\Contracts\Communities\CreatesCommunities;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use \App\Traits\ApiResponses;

class CommunityApiController extends Controller
{
  use ApiResponses;

  public function create(Request $request): JsonResponse {
    Log::info('Processing API request to create a new community.');
    Log::debug("User: ", [$request->user()]);

    // FIXME validate API input

    $creator = app(CreatesCommunities::class);

    Log::info("Input", [$request->all()]);

    try {
      $creator->create($request->user(), $request->all());
    } catch(\Exception $e) {
      Log::warning("Failed to create the new community", [$e->getMessage()]);
      // FIXME validate exception
    }

    return $this->success('Community created successfully', Response::HTTP_CREATED);
  }
}
