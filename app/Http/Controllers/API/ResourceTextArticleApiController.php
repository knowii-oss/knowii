<?php

namespace App\Http\Controllers\API;

use App\Contracts\Resources\CreatesTextResources;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityResourceResource;
use App\Models\Community;
use App\Models\CommunityResourceCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponses;
use Illuminate\Support\Facades\Log;

class ResourceTextArticleApiController extends Controller
{
  use ApiResponses;

  /**
   * Store a newly created resource in storage.
   *
   * @param Request $request
   * @param Community $community
   * @param CommunityResourceCollection $communityResourceCollection
   * @return JsonResponse
   */
  final public function store(Request $request, Community $community, CommunityResourceCollection $communityResourceCollection): JsonResponse
  {
    Log::info('Processing API request to create a new text article resource.');
    Log::debug("User: ", [$request->user()]);

    // Filter the input to only include the fields that are needed/accepted
    $input = $request->only(['url', 'level']);
    Log::debug("Input", [$input]);

    $creator = app(CreatesTextResources::class);

    $createdResource = $creator->create(
      $request->user(),
      $community,
      $communityResourceCollection,
      $input
    );

    $createdResource->load([
      // those correspond to method names on the CommunityResource model
      'resource',
      'curator',
      'textArticle',
      'collection',
    ]);

    return self::created(
      new CommunityResourceResource($createdResource),
      "Resource created successfully"
    );
  }
}
