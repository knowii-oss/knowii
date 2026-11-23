<?php

namespace App\Http\Controllers\API;

use App\Contracts\CommunityMembers\JoinsCommunities;
use App\Contracts\CommunityMembers\LeavesCommunities;
use App\Contracts\CommunityMembers\RemovesCommunityMembers;
use App\Contracts\CommunityMembers\UpdatesCommunityMemberRoles;
use App\Enums\KnowiiMemberSort;
use App\Http\Controllers\Controller;
use App\Http\Resources\MemberWithRoleResource;
use App\Models\Community;
use App\Models\User;
use App\Support\CommunityMemberQuery;
use App\Support\MemberTagsOfMembers;
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
     * List the members of the community with their role. Without `sort`: owner first, then admins, moderators and
     * members. With a query: the members whose username, or a word of their name, starts with it. Each entry holds the
     * member tags that the caller may see (`tags`, by name): every tag for the people who manage the members, the tags
     * that the managers show to the members for another member. The list leaves out the members who chose to hide from
     * it (#1466), except the owner, the admins, the moderators and the caller; the people who manage the members get
     * everyone. `in_member_list` tells whether the other members find the person in the list. Limited per minute.
     */
    #[QueryParameter('query', description: 'Beginning of the username or of a word of the name. The server keeps its first 100 characters. Empty: every member', type: 'string', example: 'ada')]
    #[QueryParameter('sort', description: 'The order. role: owner, admins, moderators, members, each by username. name: the name from A to Z. newest: the date of joining, the newest first. Any other value: role', type: "'role'|'name'|'newest'", default: 'role', example: 'name')]
    #[QueryParameter('limit', description: 'Number of members (default 10, maximum 100)', type: 'int', example: 20)]
    #[QueryParameter('offset', description: 'Number of members to skip', type: 'int', example: 0)]
    final public function index(Request $request, Community $community): JsonResponse
    {
        Gate::authorize('view', $community);

        // A value that is not a text (`?query[]=a`, `?sort[]=name`) counts as no value
        $query = $request->query('query');
        $sort = $request->query('sort');
        $query = CommunityMemberQuery::searchText(is_string($query) ? $query : '');
        $sort = KnowiiMemberSort::forApi(is_string($sort) ? $sort : null);
        $page = Pagination::fromRequest($request)->apply(CommunityMemberQuery::for($community, $request->user() ?? throw new AuthenticationException, $query, $sort));
        // One query for the tags of the whole page (#1389, ADR 0060)
        MemberTagsOfMembers::attach($community, $page['items'], $request->user());

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
        $caller = $request->user() ?? throw new AuthenticationException;
        app(UpdatesCommunityMemberRoles::class)->update($caller, $community, $member, $request->only(['role']));

        // The caller manages the members, so the member is in the list that the caller reads (#1466)
        /** @var User $updated */
        $updated = CommunityMemberQuery::for($community, $caller)->where('users.cuid', $member)->firstOrFail();
        MemberTagsOfMembers::attach($community, [$updated], $request->user());

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
     * Join an open community by yourself, as a member (#1486, ADR 0044). The community must be open (`join_policy`), and
     * your email address verified. You must not be a member yet, and no manager must have removed you: ask the managers for
     * an invitation then. Joining gives no access group. Every refusal is a 422 with a message. Returns the community as a
     * member sees it. Limited per minute.
     *
     * @throws AuthenticationException
     */
    final public function join(Request $request, Community $community): JsonResponse
    {
        $joined = app(JoinsCommunities::class)->join($request->user() ?? throw new AuthenticationException, $community);

        return self::created($joined->loadCount('users')->toResource()->withLargeFields(), 'Community joined');
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
