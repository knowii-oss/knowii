<?php

namespace App\Http\Controllers\Inertia;

use App\Models\Community;
use App\Support\SpaceTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\RedirectsActions;

class CommunityController extends Controller
{
    use RedirectsActions;

    /**
     * Show the community screen.
     */
    public function show(Request $request, string $slug): Response
    {
        $community = Community::where('slug', $slug)->firstOrFail();

        $communityResourceCollections = $community->communityResourceCollections()
            ->orderBy('name')
            ->get();

        // WARNING: this is an associative array. This needs to be converted using array_values
        $recentResources = $community->recentResources();

        Gate::authorize('view', $community);

        // Disable wrapping for the data we return to the frontend from this controller
        // This lets us use the API Resources classes without the wrapping that is normally applied
        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by the page
        return Jetstream::inertia()->render($request, 'Communities/Show', [
            // WARNING: The props passed here must remain aligned with the props expected by the page
            'community' => $community->toResource()->withLargeFields(),

            'resourceCollections' => $communityResourceCollections->toResourceCollection(),
            'recentResources' => $recentResources->toResourceCollection(),
            'spaceGroups' => SpaceTree::for($community, $request->user())->toResourceCollection(),

            'permissions' => $this->getCommunityPermissions($community),
        ]);
    }

    /**
     * Show the community settings screen.
     */
    public function settings(Request $request, string $slug): Response
    {
        $community = (new Community)->where('slug', $slug)->firstOrFail();

        Gate::authorize('manage', $community);

        // Disable wrapping for the data we return to the frontend from this controller
        // This lets us use the API Resources classes without the wrapping that is normally applied
        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by the page
        return Jetstream::inertia()->render($request, 'Communities/Settings', [
            // WARNING: The props passed here must remain aligned with the props expected by the page
            'community' => $community->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $request->user())->toResourceCollection(),

            'permissions' => $this->getCommunityPermissions($community),
        ]);
    }

    /**
     * Get the permissions for a community.
     *
     *
     * @return array<string,bool>
     */
    private function getCommunityPermissions(Community $community): array
    {
        // WARNING: The props passed here must remain aligned with the props defined in community.schema.ts
        return [
            'canManageCommunity' => Gate::check('manage', $community),
            'canManageSpaces' => Gate::check('manageSpaces', $community),
            'canUpdateCommunity' => Gate::check('update', $community),
            'canDeleteCommunity' => Gate::check('delete', $community),

            'canAddCommunityMembers' => Gate::check('addCommunityMember', $community),
            'canUpdateCommunityMembers' => Gate::check('updateCommunityMember', $community),
            'canRemoveCommunityMembers' => Gate::check('removeCommunityMember', $community),

            'canCreateResourceCollection' => Gate::check('createResourceCollection', $community),
            'canUpdateResourceCollection' => Gate::check('updateResourceCollection', $community),
            'canDeleteResourceCollection' => Gate::check('deleteResourceCollection', $community),

            'canCreateResource' => Gate::check('createResource', $community),
            'canUpdateResource' => Gate::check('updateResource', $community),
            'canDeleteResource' => Gate::check('deleteResource', $community),
        ];
    }
}
