<?php

namespace App\Http\Controllers\API;

use App\Contracts\AccessGroups\AddsAccessGroupMembers;
use App\Contracts\AccessGroups\CreatesAccessGroups;
use App\Contracts\AccessGroups\DeletesAccessGroups;
use App\Contracts\AccessGroups\RemovesAccessGroupMembers;
use App\Contracts\AccessGroups\SetsAccessGroupSpaceGroups;
use App\Contracts\AccessGroups\UpdatesAccessGroups;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use App\Models\AccessGroup;
use App\Models\Community;
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
 * Access groups (#1241, ADR 0025): named groups of members that open the spaces with the "groups" visibility. The
 * owner and the admins manage the groups; moderators with the "manage members" right add and remove their members.
 * Another system (the Knowii account, a payment tool) keeps a group in step with a token that has groups:write.
 * Changes are delegated to the actions (ADR 0006).
 */
class AccessGroupApiController extends Controller
{
    use ApiResponses;

    /**
     * List the access groups of the community, by name, with their member and space counts.
     */
    final public function index(Community $community): JsonResponse
    {
        Gate::authorize('viewAccessGroups', $community);

        $groups = $community->accessGroups()->withCount(['members', 'spaces', 'spaceGroups'])->with('spaceGroups')->orderBy('name')->get();

        return self::success('Access groups', null, $groups->toResourceCollection());
    }

    /**
     * Create an access group (owner and admins).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the group (2 to 64 characters), unique in the community', required: true, type: 'string', example: 'Paid members')]
    #[BodyParameter('description', description: 'What the group is for (up to 500 characters)', required: false, type: 'string', example: 'Members with a paid plan')]
    final public function store(Request $request, Community $community): JsonResponse
    {
        $group = app(CreatesAccessGroups::class)->create($request->user() ?? throw new AuthenticationException, $community, $request->only(['name', 'description']));

        return self::created($group->loadCount(['members', 'spaces', 'spaceGroups'])->load('spaceGroups')->toResource(), 'Access group created');
    }

    /**
     * Rename an access group or change its description (owner and admins).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('name', description: 'Name of the group (2 to 64 characters), unique in the community', required: false, type: 'string', example: 'Paid members')]
    #[BodyParameter('description', description: 'What the group is for (up to 500 characters)', required: false, type: 'string')]
    final public function update(Request $request, Community $community, AccessGroup $accessGroup): JsonResponse
    {
        $group = app(UpdatesAccessGroups::class)->update($request->user() ?? throw new AuthenticationException, $accessGroup, $request->only(['name', 'description']));

        return self::success('Access group updated', null, $group->loadCount(['members', 'spaces', 'spaceGroups'])->load('spaceGroups')->toResource());
    }

    /**
     * Delete an access group (owner and admins). Its members lose the spaces that only it opened.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, AccessGroup $accessGroup): Response
    {
        app(DeletesAccessGroups::class)->delete($request->user() ?? throw new AuthenticationException, $accessGroup);

        return self::deleted();
    }

    /**
     * List the members of an access group, by name.
     */
    #[QueryParameter('limit', description: 'Number of members (default 20, maximum 100)', type: 'int', example: 20)]
    #[QueryParameter('offset', description: 'Number of members to skip', type: 'int', example: 0)]
    final public function members(Request $request, Community $community, AccessGroup $accessGroup): JsonResponse
    {
        Gate::authorize('viewAccessGroups', $community);

        $page = Pagination::fromRequest($request)->apply($accessGroup->members()->getQuery()->orderBy('users.name')->orderBy('users.id'));

        return self::success('Access group members', $page['metadata'], $page['items']->toResourceCollection(AuthorResource::class));
    }

    /**
     * Add a member of the community to the group, named by exactly one of member (user cuid), username or email. Adding
     * a member twice changes nothing, so a sync can send the same list again.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('member', description: 'Cuid of the user', required: false, type: 'string')]
    #[BodyParameter('username', description: 'Username of the member', required: false, type: 'string', example: 'ada-lovelace')]
    #[BodyParameter('email', description: 'Email address of the member (for a sync from another system)', required: false, type: 'string', format: 'email', example: 'ada@example.com')]
    final public function addMember(Request $request, Community $community, AccessGroup $accessGroup): JsonResponse
    {
        $member = app(AddsAccessGroupMembers::class)->add($request->user() ?? throw new AuthenticationException, $accessGroup, $request->only(['member', 'username', 'email']));

        return self::success('Member added to the group', null, $member->toResource(AuthorResource::class));
    }

    /**
     * Remove a member from the group.
     *
     * @throws AuthenticationException
     */
    final public function removeMember(Request $request, Community $community, AccessGroup $accessGroup, string $member): Response
    {
        app(RemovesAccessGroupMembers::class)->remove($request->user() ?? throw new AuthenticationException, $accessGroup, $member);

        return self::deleted();
    }

    /**
     * List the space groups that the group opens whole, in navigation order (#1422, ADR 0041).
     */
    final public function spaceGroups(Community $community, AccessGroup $accessGroup): JsonResponse
    {
        Gate::authorize('viewAccessGroups', $community);

        $groups = $accessGroup->spaceGroups()->orderBy('position')->orderBy('space_groups.id')->get();

        return self::success('Space groups of the access group', null, $groups->toResourceCollection());
    }

    /**
     * Replace the space groups that the group opens whole (#1422, ADR 0041). Its members read every space of those
     * space groups that has the groups visibility, the spaces added later included. An empty list removes every link.
     * Owner, admins, and moderators with the manage spaces right.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('space_groups', description: 'Cuids of the space groups of the community: replaces the current ones. An empty list removes them all', required: true, type: 'array<string>', example: ['q8kspacegroupstarthere01'])]
    final public function setSpaceGroups(Request $request, Community $community, AccessGroup $accessGroup): JsonResponse
    {
        $group = app(SetsAccessGroupSpaceGroups::class)->set($request->user() ?? throw new AuthenticationException, $accessGroup, $request->only(['space_groups']));

        return self::success('Space groups of the access group saved', null, $group->spaceGroups()->orderBy('position')->orderBy('space_groups.id')->get()->toResourceCollection());
    }
}
