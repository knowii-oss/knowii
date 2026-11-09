<?php

namespace App\Http\Controllers\Inertia;

use App\Enums\KnowiiSpaceType;
use App\Http\Resources\CommunityResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\SpaceGroupResource;
use App\Http\Resources\SpaceResource;
use App\Models\Community;
use App\Support\Pagination;
use App\Support\SpaceTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class SpaceController extends Controller
{
    // WARNING: aligned with POSTS_PER_PAGE (constants.ts)
    public const int POSTS_PER_PAGE = 20;

    /**
     * Show a space of a community.
     */
    public function show(Request $request, string $communitySlug, string $spaceSlug): Response
    {
        $community = Community::where('slug', $communitySlug)->firstOrFail();
        $space = $community->spaces()->where('slug', $spaceSlug)->firstOrFail();

        Gate::authorize('view', $space);

        JsonResource::withoutWrapping();

        // Discussion spaces: the first page of posts (the page loads more through the API)
        $posts = null;
        if ($space->type === KnowiiSpaceType::Discussion) {
            $page = (new Pagination(self::POSTS_PER_PAGE, 0))->apply($space->posts()->with(['author', 'space', 'reactions'])->withCount('comments')->forSpacePage());
            $posts = [
                'items' => PostResource::collection($page['items']),
                'pagination' => $page['metadata']['pagination'],
            ];
        }

        // WARNING: The props passed here must remain aligned with the props expected by the page (Pages/Spaces/Show.tsx)
        return Jetstream::inertia()->render($request, 'Spaces/Show', [
            'posts' => $posts,
            'community' => new CommunityResource($community, true),
            'space' => new SpaceResource($space, true),
            'spaceGroups' => SpaceGroupResource::collection(SpaceTree::for($community, $request->user())),
            'permissions' => [
                'canManageSpaces' => Gate::check('manageSpaces', $community),
                'canContribute' => Gate::check('contribute', $space),
                'canModerate' => Gate::check('moderate', $space),
                // Members react to and comment what they can read
                'canReact' => $request->user()?->belongsToCommunity($community) ?? false,
            ],
        ]);
    }
}
