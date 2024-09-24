<?php

namespace App\Http\Controllers\Inertia;

use App\Knowii;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\RedirectsActions;

class CommunityController extends Controller
{
    use RedirectsActions;

    /**
     * Show the community management screen.
     *
     * @param  Request  $request
     * @param  string $slug
     * @return Response
     */
    public function show(Request $request, string $slug): Response
    {
        $community = Knowii::newCommunityModel()->where('slug', $slug)->firstOrFail();

        Gate::authorize('view', $community);

        // WARNING: The props passed here must remain aligned with the props expected by the page
        return Jetstream::inertia()->render($request, 'Communities/Show', [
            'community' => $community->load('owner', 'users', 'communityInvitations'),
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
