<?php

namespace App\Http\Controllers\API;

use App\Contracts\CommunityMembers\LiftsCommunityJoinBlocks;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityJoinBlockResource;
use App\Models\Community;
use App\Support\Pagination;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The people whom a manager removed from a community, who cannot join it again by themselves (#1486, ADR 0044). Only the
 * managers of the members read the list and lift a block. The change is delegated to the action (ADR 0006).
 */
class CommunityJoinBlockApiController extends Controller
{
    use ApiResponses;

    /**
     * List the people whom a manager removed, the most recent removal first (owner, admins, moderators with the "manage
     * members" right).
     */
    #[QueryParameter('limit', description: 'Number of people (default 20, maximum 100)', type: 'int', example: 20)]
    #[QueryParameter('offset', description: 'Number of people to skip', type: 'int', example: 0)]
    final public function index(Request $request, Community $community): JsonResponse
    {
        Gate::authorize('removeCommunityMember', $community);

        $page = Pagination::fromRequest($request)->apply(
            $community->joinBlocks()->with('user')->orderByDesc('created_at')->orderByDesc('id'),
        );

        return self::success('Join blocks', $page['metadata'], $page['items']->toResourceCollection(CommunityJoinBlockResource::class));
    }

    /**
     * "May join again": the person can join the community by themselves again (when it is open). Owner, admins,
     * moderators with the "manage members" right. Returns 204; a person without a block gives a 404.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, string $member): Response
    {
        app(LiftsCommunityJoinBlocks::class)->lift($request->user() ?? throw new AuthenticationException, $community, $member);

        return self::deleted();
    }
}
