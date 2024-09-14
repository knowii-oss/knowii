<?php

namespace App\Http\Controllers\API;

use App\Contracts\Communities\CreatesCommunities;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use \App\Traits\ApiResponses;

class CommunityApiController extends Controller
{
  use ApiResponses;

  final public function store(Request $request): JsonResponse {
    Log::info('Processing API request to create a new community.');
    Log::debug("User: ", [$request->user()]);

    $creator = app(CreatesCommunities::class);

    Log::info("Input", [$request->all()]);

    //throw new BusinessException("The community name is already taken", null, null, ["name" => "The community name is already taken"]);
    //throw new TechnicalException("Could not create the new community");

    //return self::technicalIssue("Could not create the new community");

    $createdCommunity = $creator->create($request->user(), $request->all());
    return self::created(new CommunityResource($createdCommunity), "Community created successfully");
  }
}
