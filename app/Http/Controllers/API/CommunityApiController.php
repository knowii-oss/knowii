<?php

namespace App\Http\Controllers\API;

use App\Contracts\Communities\CreatesCommunities;
use App\Contracts\Communities\DeletesCommunities;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityResource;
use App\Models\Community;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

use \App\Traits\ApiResponses;

class CommunityApiController extends Controller
{
  use ApiResponses;

  final public function store(Request $request): JsonResponse
  {
    Log::info('Processing API request to create a new community.');
    Log::debug("User: ", [$request->user()]);

    // Filter the input to only include the fields that are needed/accepted
    $input = $request->only(['name', 'description', 'visibility']);
    Log::debug("Input", [$input]);

    $creator = app(CreatesCommunities::class);
    $createdItem = $creator->create($request->user(), $input);
    return self::created(new CommunityResource($createdItem, true), "Community created successfully");
  }

  final public function destroy(Request $request, Community $community): Response
  {
    Log::info('Processing API request to delete a community.');
    Log::debug("User: ", [$request->user()]);

    $deleter = app(DeletesCommunities::class);
    $deleter->delete($request->user(), $community);

    return self::deleted();
  }
}
