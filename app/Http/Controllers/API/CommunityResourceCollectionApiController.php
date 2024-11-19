<?php

namespace App\Http\Controllers\API;

use App\Contracts\CommunityResourceCollections\CreatesCommunityResourceCollections;
use App\Contracts\CommunityResourceCollections\DeletesCommunityResourceCollections;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityResourceCollectionResource;
use App\Models\Community;
use App\Models\CommunityResourceCollection;
use App\Traits\ApiResponses;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CommunityResourceCollectionApiController extends Controller
{
    use ApiResponses;

    /**
     * @throws AuthenticationException
     */
    final public function store(Request $request, Community $community): JsonResponse
    {
        Log::info('Processing API request to create a new community resource collection.');
        Log::debug('User: ', [$request->user()]);

        // Filter the input to only include the fields that are needed/accepted
        $input = $request->only(['name', 'description']);
        Log::debug('Input', [$input]);

        $creator = app(CreatesCommunityResourceCollections::class);
        $createdItem = $creator->create($request->user() ?? throw new AuthenticationException('User must be authenticated'), $community, $input);

        return self::created(
            new CommunityResourceCollectionResource($createdItem, true),
            'Resource collection created successfully'
        );
    }

    /**
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, CommunityResourceCollection $communityResourceCollection): Response
    {
        Log::info('Processing API request to delete a resource collection.');
        Log::debug('User: ', [$request->user()]);

        $deleter = app(DeletesCommunityResourceCollections::class);
        $deleter->delete($request->user() ?? throw new AuthenticationException('User must be authenticated'), $communityResourceCollection);

        return self::deleted();
    }
}
