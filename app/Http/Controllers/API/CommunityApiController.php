<?php

namespace App\Http\Controllers\API;

use App\Contracts\Communities\CreatesCommunities;
use App\Contracts\Communities\DeletesCommunities;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class CommunityApiController extends Controller
{
    use ApiResponses;

    /**
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the community (3 to 64 characters: letters, digits, spaces, dashes, apostrophes, @)', required: true, type: 'string', example: 'Knowledge Builders')]
    #[BodyParameter('description', description: 'Short description (up to 255 characters)', required: false, type: 'string')]
    #[BodyParameter('visibility', description: 'personal, private or public', required: true, type: 'string', example: 'public')]
    final public function store(Request $request): JsonResponse
    {
        Log::info('Processing API request to create a new community.');
        Log::debug('User: ', [$request->user()]);

        // Filter the input to only include the fields that are needed/accepted
        $input = $request->only(['name', 'description', 'visibility']);
        Log::debug('Input', [$input]);

        $creator = app(CreatesCommunities::class);
        $createdItem = $creator->create($request->user() ?? throw new AuthenticationException('User must be authenticated'), $input);

        return self::created($createdItem->toResource()->withLargeFields(), 'Community created successfully');
    }

    /**
     * @throws AuthenticationException
     */
    /**
     * Get a community with its description, its moderator rights and its number of members (#1135). Members only.
     */
    final public function show(Community $community): JsonResponse
    {
        Gate::authorize('view', $community);

        return self::success('Community', null, $community->loadCount('users')->toResource()->withLargeFields());
    }

    final public function destroy(Request $request, Community $community): Response
    {
        Log::info('Processing API request to delete a community.');
        Log::debug('User: ', [$request->user()]);

        $deleter = app(DeletesCommunities::class);
        $deleter->delete($request->user() ?? throw new AuthenticationException('User must be authenticated'), $community);

        return self::deleted();
    }
}
