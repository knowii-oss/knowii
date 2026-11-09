<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Resources\CommentResource;
use App\Http\Resources\CommunityResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\SpaceGroupResource;
use App\Http\Resources\SpaceResource;
use App\Models\Community;
use App\Support\CommentThread;
use App\Support\SpaceTree;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;

class PostController extends Controller
{
    /**
     * Show a post of a discussion space.
     */
    public function show(Request $request, string $communitySlug, string $spaceSlug, string $postSlug): Response
    {
        $community = Community::where('slug', $communitySlug)->firstOrFail();
        $space = $community->spaces()->where('slug', $spaceSlug)->firstOrFail();
        $post = $space->posts()->with(['author', 'space', 'reactions'])->withCount('comments')->where('slug', $postSlug)->firstOrFail();

        Gate::authorize('view', $post);

        JsonResource::withoutWrapping();

        // WARNING: The props passed here must remain aligned with the props expected by the page (Pages/Posts/Show.tsx)
        return Jetstream::inertia()->render($request, 'Posts/Show', [
            'community' => new CommunityResource($community, true),
            'space' => new SpaceResource($space, true),
            'spaceGroups' => SpaceGroupResource::collection(SpaceTree::for($community, $request->user())),
            'post' => new PostResource($post, true),
            'comments' => CommentResource::collection(CommentThread::for($post)),
            'permissions' => [
                'canManageSpaces' => Gate::check('manageSpaces', $community),
                'canContribute' => Gate::check('contribute', $space),
                'canModerate' => Gate::check('moderate', $space),
                'canUpdatePost' => Gate::check('update', $post),
                'canDeletePost' => Gate::check('delete', $post),
                'canPinPost' => Gate::check('pin', $post),
                'canComment' => Gate::check('comment', $post),
            ],
        ]);
    }
}
