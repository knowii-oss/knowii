<?php

namespace App\Http\Controllers\Inertia;

use App\Enums\KnowiiSpaceType;
use App\Models\Community;
use App\Support\SpaceTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class CommunityResourceController extends Controller
{
    /**
     * Show a resource of a resources space (ADR 0015).
     */
    public function show(Request $request, string $communitySlug, string $spaceSlug, string $resourceSlug): Response
    {
        $community = Community::where('slug', $communitySlug)->firstOrFail();
        $space = $community->spaces()->where('slug', $spaceSlug)->where('type', KnowiiSpaceType::Resources)->firstOrFail();

        Gate::authorize('view', $space);

        $resource = $space->resources()->where('slug', $resourceSlug)->with(['curator', 'resource', 'textArticle', 'space'])->firstOrFail();

        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by the page (Pages/Resources/Show.tsx)
        return Jetstream::inertia()->render($request, 'Resources/Show', [
            'community' => $community->toResource()->withLargeFields(),
            'space' => $space->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $request->user())->toResourceCollection(),
            'resource' => $resource->toResource()->withLargeFields(),
        ]);
    }
}
