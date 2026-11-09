<?php

namespace App\Http\Controllers\Inertia;

use App\Enums\KnowiiSpaceType;
use App\Models\Community;
use App\Models\Space;
use App\Models\SpaceGroup;
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

        Gate::authorize('view', $community);

        $spaceGroups = SpaceTree::for($community, $request->user());

        // Recent resources: only from the resources spaces that the member can see (ADR 0015)
        $resourcesSpaceIds = $spaceGroups
            ->flatMap(fn (SpaceGroup $group) => $group->spaces)
            ->filter(fn (Space $space) => $space->type === KnowiiSpaceType::Resources)
            ->map(fn (Space $space) => $space->id)
            ->values()
            ->all();
        $recentResources = $community->recentResources($resourcesSpaceIds);

        // Disable wrapping for the data we return to the frontend from this controller
        // This lets us use the API Resources classes without the wrapping that is normally applied
        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by the page
        return Jetstream::inertia()->render($request, 'Communities/Show', [
            // WARNING: The props passed here must remain aligned with the props expected by the page
            'community' => $community->toResource()->withLargeFields(),

            'recentResources' => $recentResources->toResourceCollection(),
            'spaceGroups' => $spaceGroups->toResourceCollection(),

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
        ];
    }
}
