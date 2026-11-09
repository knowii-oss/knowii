<?php

namespace App\Http\Controllers\API;

use App\Contracts\SpaceGroups\CreatesSpaceGroups;
use App\Contracts\SpaceGroups\DeletesSpaceGroups;
use App\Contracts\SpaceGroups\ReordersSpaceGroups;
use App\Contracts\SpaceGroups\UpdatesSpaceGroups;
use App\Http\Controllers\Controller;
use App\Http\Resources\SpaceGroupResource;
use App\Models\Community;
use App\Models\SpaceGroup;
use App\Support\SpaceTree;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Space groups of a community (#1173). Changes are delegated to the actions (ADR 0006).
 */
class SpaceGroupApiController extends Controller
{
    use ApiResponses;

    /**
     * List the space groups of the community with their spaces, limited to what the user can see.
     */
    final public function index(Request $request, Community $community): JsonResponse
    {
        Gate::authorize('view', $community);

        $groups = SpaceTree::for($community, $request->user());

        return self::success('Space groups', null, SpaceGroupResource::collection($groups));
    }

    /**
     * Create a space group at the end of the navigation.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the space group (2 to 64 characters)', required: true, type: 'string', example: 'Start Here')]
    final public function store(Request $request, Community $community): JsonResponse
    {
        $input = $request->only(['name']);

        $spaceGroup = app(CreatesSpaceGroups::class)->create($request->user() ?? throw new AuthenticationException, $community, $input);

        return self::created(new SpaceGroupResource($spaceGroup), 'Space group created successfully');
    }

    /**
     * Rename a space group.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the space group (2 to 64 characters)', required: true, type: 'string', example: 'Start Here')]
    final public function update(Request $request, Community $community, SpaceGroup $spaceGroup): JsonResponse
    {
        $input = $request->only(['name']);

        $spaceGroup = app(UpdatesSpaceGroups::class)->update($request->user() ?? throw new AuthenticationException, $spaceGroup, $input);

        return self::success('Space group updated successfully', null, new SpaceGroupResource($spaceGroup));
    }

    /**
     * Delete an empty space group.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, SpaceGroup $spaceGroup): Response
    {
        app(DeletesSpaceGroups::class)->delete($request->user() ?? throw new AuthenticationException, $spaceGroup);

        return self::deleted();
    }

    /**
     * Set the order of all the space groups of the community.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('space_group_cuids', description: 'Every space group of the community, once, in the new order', required: true, type: 'array<string>')]
    final public function reorder(Request $request, Community $community): JsonResponse
    {
        $input = $request->only(['space_group_cuids']);

        $groups = app(ReordersSpaceGroups::class)->reorder($request->user() ?? throw new AuthenticationException, $community, $input);

        return self::success('Space groups reordered successfully', null, SpaceGroupResource::collection($groups));
    }
}
