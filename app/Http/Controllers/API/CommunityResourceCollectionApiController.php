<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Contracts\CommunityResourceCollections\CreatesCommunityResourceCollections;
use App\Http\Resources\CommunityResourceCollectionResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponses;
use Illuminate\Support\Facades\Log;


class CommunityResourceCollectionApiController extends Controller
{
    use ApiResponses;

    // FIXME should this receive the community or fetch it?
    final public function store(Request $request, string $communityCuid): JsonResponse
    {
        Log::info('Processing API request to create a new community resource collection.');
        Log::debug("User: ", [$request->user()]);

        Log::debug("Looking for the community", [$communityCuid]);
        $community = (new Community())->whereCuid($communityCuid)->firstOrFail();
        Log::debug("Found the community", [$community]);

        // Filter the input to only include the fields that are needed/accepted
        $input = $request->only(['name', 'description']);
        Log::debug("Input", [$input]);

        $creator = app(CreatesCommunityResourceCollections::class);
        $createdItem = $creator->create($request->user(), $community, $input);

        return self::created(
            new CommunityResourceCollectionResource($createdItem),
            "Resource collection created successfully"
        );
    }
}
