<?php

namespace App\Http\Controllers\Inertia;

use App\Contracts\CommunityMembers\AcceptsCommunityInvitations;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\CommunityInvitation;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The link of an invitation email (#1242). The route is signed and temporary; a guest signs in or creates an account
 * first, then comes back here.
 */
class CommunityInvitationController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function accept(Request $request, CommunityInvitation $invitation): RedirectResponse
    {
        try {
            $community = app(AcceptsCommunityInvitations::class)->accept($request->user() ?? throw new AuthenticationException, $invitation);
        } catch (BusinessException $e) {
            return redirect()->route('dashboard')->with('flash', ['banner' => $e->getMessage(), 'bannerStyle' => 'danger']);
        }

        return redirect()->route('communities.show', ['communitySlug' => $community->slug])
            ->with('flash', ['banner' => 'Welcome to '.$community->name, 'bannerStyle' => 'success']);
    }
}
