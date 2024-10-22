<?php

namespace App\Http\Controllers\API;

use App\Contracts\CommunityResourceCollections\DeletesCommunityResourceCollections;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Contracts\CommunityResourceCollections\CreatesCommunityResourceCollections;
use App\Http\Resources\CommunityResourceCollectionResource;
use App\Models\CommunityResourceCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponses;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CommunityResourceCollectionApiController extends Controller
{
  use ApiResponses;

  final public function store(Request $request, Community $community): JsonResponse
  {
    Log::info('Processing API request to create a new community resource collection.');
    Log::debug("User: ", [$request->user()]);

    // Filter the input to only include the fields that are needed/accepted
    $input = $request->only(['name', 'description']);
    Log::debug("Input", [$input]);

    $creator = app(CreatesCommunityResourceCollections::class);
    $createdItem = $creator->create($request->user(), $community, $input);

    return self::created(
      new CommunityResourceCollectionResource($createdItem, true),
      "Resource collection created successfully"
    );
  }

  final public function destroy(Request $request, Community $community, CommunityResourceCollection $communityResourceCollection): Response
  {
    Log::info('Processing API request to delete a resource collection.');
    Log::debug("User: ", [$request->user()]);

    $deleter = app(DeletesCommunityResourceCollections::class);
    $deleter->delete($request->user(), $communityResourceCollection);

    return self::deleted();
  }
}
