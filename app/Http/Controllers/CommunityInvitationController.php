<?php

namespace App\Http\Controllers;

use App\Contracts\Communities\AddsCommunityMembers;
use App\Knowii;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class CommunityInvitationController extends Controller
{
    /**
     * Accept a community invitation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $invitationId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function accept(Request $request, int $invitationId)
    {
        $model = Knowii::communityInvitationModel();

        $invitation = $model::whereKey($invitationId)->firstOrFail();

        app(AddsCommunityMembers::class)->add(
            $invitation->community->owner,
            $invitation->community,
            $invitation->email,
            $invitation->role
        );

        $invitation->delete();

        return redirect(config('fortify.home'))->banner(
            __('Great! You have accepted the invitation to join the following community: :community.', ['community' => $invitation->community->community]),
        );
    }

    /**
     * Cancel the given community invitation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $invitationId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, $invitationId)
    {
        $model = Knowii::communityInvitationModel();

        $invitation = $model::whereKey($invitationId)->firstOrFail();

        if (! Gate::forUser($request->user())->check('removeCommunityMember', $invitation->community)) {
            throw new AuthorizationException;
        }

        $invitation->delete();

        return back(303);
    }
}
