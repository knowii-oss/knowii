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

    /**
     * Show the community management screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string $communityCuid
     * @return \Inertia\Response
     */
    public function show(Request $request, string $communityCuid) // FIXME use the slug instead of the cuid
    {
        $community = Knowii::newCommunityModel()->whereCuid($communityCuid)->firstOrFail();

        Gate::authorize('view', $community);

        // WARNING: The props passed here must remain aligned with the props expected by the page
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
}
