<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Resources\CommunityResource;
use App\Http\Resources\SpaceGroupResource;
use App\Http\Resources\SpaceResource;
use App\Models\Community;
use App\Support\SpaceTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class SpaceController extends Controller
{
    /**
     * Show a space of a community.
     */
    public function show(Request $request, string $communitySlug, string $spaceSlug): Response
    {
        $community = Community::where('slug', $communitySlug)->firstOrFail();
        $space = $community->spaces()->where('slug', $spaceSlug)->firstOrFail();

        Gate::authorize('view', $space);

        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by the page (Pages/Spaces/Show.tsx)
        return Jetstream::inertia()->render($request, 'Spaces/Show', [
            'community' => new CommunityResource($community, true),
            'space' => new SpaceResource($space, true),
            'spaceGroups' => SpaceGroupResource::collection(SpaceTree::for($community, $request->user())),
            'permissions' => [
                'canManageSpaces' => Gate::check('manageSpaces', $community),
                'canContribute' => Gate::check('contribute', $space),
                'canModerate' => Gate::check('moderate', $space),
            ],
        ]);
    }
}
