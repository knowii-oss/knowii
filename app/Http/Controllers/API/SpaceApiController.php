<?php

namespace App\Http\Controllers\API;

use App\Contracts\Spaces\CreatesSpaces;
use App\Contracts\Spaces\DeletesSpaces;
use App\Contracts\Spaces\ReordersSpaces;
use App\Contracts\Spaces\UpdatesSpaces;
use App\Http\Controllers\Controller;
use App\Http\Resources\SpaceResource;
use App\Models\Community;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Spaces of a community (#1173). Changes are delegated to the actions (ADR 0006).
 */
class SpaceApiController extends Controller
{
    use ApiResponses;

    private const array EDITABLE_FIELDS = ['name', 'description', 'emoji', 'visibility', 'posting_policy', 'space_group_cuid'];

    /**
     * Get a space.
     */
    final public function show(Community $community, Space $space): JsonResponse
    {
        Gate::authorize('view', $space);

        return self::success('Space', null, new SpaceResource($space));
    }

    /**
     * Create a space at the end of a space group. The type cannot change later.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the space (2 to 64 characters)', required: true, type: 'string', example: 'Announcements')]
    #[BodyParameter('description', description: 'Short description (up to 255 characters)', required: false, type: 'string')]
    #[BodyParameter('emoji', description: 'One emoji shown in the navigation', required: false, type: 'string', example: '📣')]
    #[BodyParameter('visibility', description: 'public (anyone can read) or members', required: true, type: 'string', example: 'members')]
    #[BodyParameter('posting_policy', description: 'members or moderators (who can post)', required: true, type: 'string', example: 'members')]
    #[BodyParameter('space_group_cuid', description: 'Space group of the community. On update: moves the space to the end of that group', required: true, type: 'string')]
    #[BodyParameter('type', description: 'discussion, chat, pages or course. Cannot change later', required: true, type: 'string', example: 'discussion')]
    final public function store(Request $request, Community $community): JsonResponse
    {
        $input = $request->only([...self::EDITABLE_FIELDS, 'type']);

        $space = app(CreatesSpaces::class)->create($request->user() ?? throw new AuthenticationException, $community, $input);

        return self::created(new SpaceResource($space), 'Space created successfully');
    }

    /**
     * Update some fields of a space. Sending space_group_cuid moves the space to the end of that group.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the space (2 to 64 characters)', required: false, type: 'string', example: 'Announcements')]
    #[BodyParameter('description', description: 'Short description (up to 255 characters)', required: false, type: 'string')]
    #[BodyParameter('emoji', description: 'One emoji shown in the navigation', required: false, type: 'string', example: '📣')]
    #[BodyParameter('visibility', description: 'public (anyone can read) or members', required: false, type: 'string', example: 'members')]
    #[BodyParameter('posting_policy', description: 'members or moderators (who can post)', required: false, type: 'string', example: 'members')]
    #[BodyParameter('space_group_cuid', description: 'Space group of the community. On update: moves the space to the end of that group', required: false, type: 'string')]
    final public function update(Request $request, Community $community, Space $space): JsonResponse
    {
        $input = $request->only(self::EDITABLE_FIELDS);

        $space = app(UpdatesSpaces::class)->update($request->user() ?? throw new AuthenticationException, $space, $input);

        return self::success('Space updated successfully', null, new SpaceResource($space));
    }

    /**
     * Delete a space and its content.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, Space $space): Response
    {
        app(DeletesSpaces::class)->delete($request->user() ?? throw new AuthenticationException, $space);

        return self::deleted();
    }

    /**
     * Set the order of the spaces of a space group.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('space_cuids', description: 'Every space of the group, once, in the new order', required: true, type: 'array<string>')]
    final public function reorder(Request $request, Community $community, SpaceGroup $spaceGroup): JsonResponse
    {
        $input = $request->only(['space_cuids']);

        $spaces = app(ReordersSpaces::class)->reorder($request->user() ?? throw new AuthenticationException, $spaceGroup, $input);

        return self::success('Spaces reordered successfully', null, SpaceResource::collection($spaces));
    }
}
