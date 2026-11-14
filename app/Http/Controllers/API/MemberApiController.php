<?php

namespace App\Http\Controllers\API;

use App\Contracts\CommunityMembers\LeavesCommunities;
use App\Contracts\CommunityMembers\RemovesCommunityMembers;
use App\Contracts\CommunityMembers\UpdatesCommunityMemberRoles;
use App\Http\Controllers\Controller;
use App\Http\Resources\MemberWithRoleResource;
use App\Models\Community;
use App\Models\User;
use App\Support\CommunityMemberQuery;
use App\Support\Pagination;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Members of a community: the list with roles and the mention autocomplete (#1177), role changes and removals
 * (#1242, role hierarchy of ADR 0017). Changes are delegated to the actions (ADR 0006).
 */
class MemberApiController extends Controller
{
    use ApiResponses;

    /**
     * List the members of the community with their role: owner first, then admins, moderators and members. With a
     * query: the members whose username, or a word of their name, starts with it.
     */
    #[QueryParameter('query', description: 'Beginning of the username or of a word of the name. Empty: every member', type: 'string', example: 'ada')]
    #[QueryParameter('limit', description: 'Number of members (default 20, maximum 100)', type: 'int', example: 20)]
    #[QueryParameter('offset', description: 'Number of members to skip', type: 'int', example: 0)]
    final public function index(Request $request, Community $community): JsonResponse
    {
        Gate::authorize('view', $community);

        $query = trim((string) $request->query('query', ''));
        $page = Pagination::fromRequest($request)->apply(CommunityMemberQuery::for($community, $query));

        return self::success('Members', $page['metadata'], $page['items']->toResourceCollection(MemberWithRoleResource::class));
    }

    /**
     * Change the role of a member (owner, admins, moderators with the "manage members" right). Nobody changes the
     * owner; only the owner makes or changes admins; nobody acts on a member with their own role or a higher one.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('role', description: 'admin, moderator or member: lower than the role of the caller', required: true, type: 'string', example: 'moderator')]
    final public function update(Request $request, Community $community, string $member): JsonResponse
    {
        app(UpdatesCommunityMemberRoles::class)->update($request->user() ?? throw new AuthenticationException, $community, $member, $request->only(['role']));

        /** @var User $updated */
        $updated = CommunityMemberQuery::for($community)->where('users.cuid', $member)->firstOrFail();

        return self::success('Role changed', null, $updated->toResource(MemberWithRoleResource::class));
    }

    /**
     * Remove a member from the community, within the same role hierarchy. What they wrote stays.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, string $member): Response
    {
        app(RemovesCommunityMembers::class)->remove($request->user() ?? throw new AuthenticationException, $community, $member);

        return self::deleted();
    }

    /**
     * Leave the community: the current member removes their own membership (#1338). They leave its access groups and
     * lose their course progress; what they wrote stays. The owner gets a 422: they transfer the ownership first.
     *
     * @throws AuthenticationException
     */
    final public function leave(Request $request, Community $community): Response
    {
        app(LeavesCommunities::class)->leave($request->user() ?? throw new AuthenticationException, $community);

        return self::deleted();
    }
}
