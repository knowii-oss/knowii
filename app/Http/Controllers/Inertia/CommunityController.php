<?php

namespace App\Http\Controllers\Inertia;

use App\Models\Community;
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
        $community = (new Community())->where('slug', $slug)->firstOrFail();

        Gate::authorize('view', $community);

        // WARNING: The props passed here must remain aligned with the props expected by the page
        return Jetstream::inertia()->render($request, 'Communities/Show', [
            // WARNING: The props passed here must remain aligned with the props expected by the page
            'community' => $community->load('owner', 'users', 'communityInvitations'),

            // WARNING: The props passed here must remain aligned with the props defined in community.schema.ts
            'permissions' => [
                'canUpdateCommunity' => Gate::check('update', $community),
                'canDeleteCommunity' => Gate::check('delete', $community),

                'canAddCommunityMembers' => Gate::check('addCommunityMember', $community),
                'canUpdateCommunityMembers' => Gate::check('updateCommunityMember', $community),
                'canRemoveCommunityMembers' => Gate::check('removeCommunityMember', $community),
                
                'canCreateResourceCollections' => Gate::check('createResourceCollection', $community),
                'canUpdateResourceCollections' => Gate::check('updateResourceCollection', $community),
                'canDeleteResourceCollections' => Gate::check('deleteResourceCollection', $community),
            ],
        ]);
    }
}
