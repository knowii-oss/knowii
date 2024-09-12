<?php

namespace App\Http\Controllers\Inertia;

use App\Actions\Communities\ValidateCommunityDeletion;
use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\Communities\UpdatesCommunityNames;
use App\Knowii;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\RedirectsActions;

class CommunityController extends Controller
{
    use RedirectsActions;

  // FIXME refactor

    /**
     * Show the community management screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $communityId
     * @return \Inertia\Response
     */
    public function show(Request $request, $communityId)
    {
        $community = Knowii::newCommunityModel()->findOrFail($communityId);

        Gate::authorize('view', $community);

        return Jetstream::inertia()->render($request, 'Communities/Show', [
            'community' => $community->load('owner', 'users', 'communityInvitations'),
            'availableRoles' => array_values(Jetstream::$roles),
            'availablePermissions' => Jetstream::$permissions,
            'defaultPermissions' => Jetstream::$defaultPermissions,
            'permissions' => [
                'canAddCommunityMembers' => Gate::check('addCommunityMember', $community),
                'canDeleteCommunity' => Gate::check('delete', $community),
                'canRemoveCommunityMembers' => Gate::check('removeCommunityMember', $community),
                'canUpdateCommunity' => Gate::check('update', $community),
                'canUpdateCommunityMembers' => Gate::check('updateCommunityMember', $community),
            ],
        ]);
    }

    /**
     * Update the given community's name.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $communityId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $communityId)
    {
        $community = Knowii::newCommunityModel()->findOrFail($communityId);

        app(UpdatesCommunityNames::class)->update($request->user(), $community, $request->all());

        return back(303);
    }

    /**
     * Delete the given community.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $communityId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, int $communityId)
    {
        $community = Knowii::newCommunityModel()->findOrFail($communityId);

        app(ValidateCommunityDeletion::class)->validate($request->user(), $community);

        $deleter = app(DeletesCommunities::class);

        $deleter->delete($community);

        return $this->redirectPath($deleter);
    }
}
