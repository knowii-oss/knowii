<?php

namespace App\Http\Controllers\API;

use App\Contracts\CommunityMembers\CancelsCommunityInvitations;
use App\Contracts\CommunityMembers\InvitesCommunityMembers;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
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
     * List the pending invitations, newest first (owner, admins, moderators with the "manage members" right).
     */
    final public function index(Community $community): JsonResponse
    {
        Gate::authorize('addCommunityMember', $community);

        $invitations = $community->communityInvitations()->latest()->latest('id')->get();

        return self::success('Invitations', null, $invitations->toResourceCollection());
    }

    /**
     * Invite an email address with a role lower than the role of the caller, and send the invitation email. Inviting
     * the same address again replaces its invitation.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('email', description: 'Email address of the person to invite', required: true, type: 'string', format: 'email', example: 'grace@example.com')]
    #[BodyParameter('role', description: 'admin, moderator or member: lower than the role of the caller', required: true, type: 'string', example: 'member')]
    final public function store(Request $request, Community $community): JsonResponse
    {
        $invitation = app(InvitesCommunityMembers::class)->invite($request->user() ?? throw new AuthenticationException, $community, $request->only(['email', 'role']));

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
