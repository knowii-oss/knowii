<?php

namespace App\Http\Controllers\API;

use App\Constants;
use App\Contracts\CommunityMembers\CancelsCommunityInvitations;
use App\Contracts\CommunityMembers\InvitesCommunityMembers;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use App\Support\InvitationGrants;
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
 * Invitations to a community (#1242): an email address with a role, accepted from the signed link of the email.
 * Changes are delegated to the actions (ADR 0006).
 */
class CommunityInvitationApiController extends Controller
{
    use ApiResponses;

    /**
     * List the pending invitations, newest first (owner, admins, moderators with the "manage members" right), with the
     * access groups and the member tags that each gives at the join.
     */
    #[QueryParameter('limit', description: 'Number of invitations (default 10, maximum 1000)', type: 'int', example: 10)]
    #[QueryParameter('offset', description: 'Number of invitations to skip', type: 'int', example: 0)]
    final public function index(Request $request, Community $community): JsonResponse
    {
        Gate::authorize('addCommunityMember', $community);

        $page = Pagination::fromRequest($request, Constants::PAGINATION_DEFAULT_LIMIT, Constants::MAX_INVITATIONS_PAGE_LIMIT)
            ->apply($community->communityInvitations()->latest()->latest('id'));
        $reader = $request->user();
        InvitationGrants::loadFor($page['items'], $community, $reader instanceof User ? $reader : null);

        return self::success('Invitations', $page['metadata'], $page['items']->toResourceCollection());
    }

    /**
     * Invite an email address with a role lower than the role of the caller, and send the invitation email. Inviting
     * the same address again replaces its invitation. The invitation can carry access groups (with the "manage the
     * members of access groups" right, and `groups:write` for a token) and member tags (with the "assign member tags"
     * right, and `member-tags:write` for a token): the person gets them when they accept it. A personal community
     * invites nobody. An account, a community and the instance send at most a number of invitation mails a day (1000
     * by default): past a daily cap, 422 with the time from when it works again. While the instance holds its mail, an
     * invitation is refused: 422 with the time from when it works again.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('email', description: 'Email address of the person to invite', required: true, type: 'string', format: 'email', example: 'grace@example.com')]
    #[BodyParameter('role', description: 'admin, moderator or member: lower than the role of the caller', required: true, type: 'string', example: 'member')]
    #[BodyParameter('access_groups', description: 'Cuids of access groups of the community that the person gets at the join (at most 50, a duplicate counts once)', required: false, type: 'array<string>', example: ['clx8ag0000000paid000000'])]
    #[BodyParameter('member_tags', description: 'Cuids of member tags of the community that the person gets at the join (at most 20, a duplicate counts once)', required: false, type: 'array<string>', example: ['clx8mt0000000found00000'])]
    final public function store(Request $request, Community $community): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        $invitation = app(InvitesCommunityMembers::class)->invite($user, $community, $request->only(['email', 'role', 'access_groups', 'member_tags']));
        InvitationGrants::loadFor([$invitation], $community, $user);

        return self::created($invitation->toResource(), 'Invitation sent');
    }

    /**
     * Cancel a pending invitation: its link stops working.
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, CommunityInvitation $communityInvitation): Response
    {
        app(CancelsCommunityInvitations::class)->cancel($request->user() ?? throw new AuthenticationException, $community, $communityInvitation);

        return self::deleted();
    }
}
