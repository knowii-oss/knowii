<?php

namespace App\Http\Controllers\Inertia;

use App\Actions\Communities\UpdateCommunityMemberRole;
use App\Contracts\Communities\InvitesCommunityMembers;
use App\Contracts\Communities\RemovesCommunityMembers;
use App\Knowii;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Jetstream;

class CommunityMemberController extends Controller
{
    /**
     * Add a new member to a community.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $communityId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, $communityId)
    {
        $community = Knowii::newCommunityModel()->findOrFail($communityId);

        app(InvitesCommunityMembers::class)->invite(
                $request->user(),
                $community,
                $request->email ?: '',
                $request->role
            );

        return back(303);
    }

    /**
     * Update the given community member's role.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $communityId
     * @param  int  $userId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $communityId, $userId)
    {
        app(UpdateCommunityMemberRole::class)->update(
            $request->user(),
            Knowii::newCommunityModel()->findOrFail($communityId),
            $userId,
            $request->role
        );

        return back(303);
    }

    /**
     * Remove the given user from the given community.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $communityId
     * @param  int  $userId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, $communityId, $userId)
    {
        $community = Knowii::newCommunityModel()->findOrFail($communityId);

        app(RemovesCommunityMembers::class)->remove(
            $request->user(),
            $community,
            $user = Jetstream::findUserByIdOrFail($userId)
        );

        if ($request->user()->id === $user->id) {
            return redirect(config('fortify.home'));
        }

        return back(303);
    }
}
