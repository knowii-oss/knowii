<?php

namespace App\Http\Controllers\Inertia;

use App\Contracts\Notifications\MarksNotificationsSeen;
use App\Contracts\Notifications\TracksSpaceVisits;
use App\Support\CommentThread;
use App\Support\SpaceAccess;
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
        // The access to the space comes before the post is read
        [$community, $space] = SpaceAccess::forPage($request, $communitySlug, $spaceSlug);
        $post = $space->posts()->with(['author', 'space', 'reactions'])->withCount('comments')->where('slug', $postSlug)->firstOrFail();

        // A member who opens the space has visited it: the dot of the navigation goes (#1476). Before the navigation is built
        if ($request->user() !== null) {
            app(TracksSpaceVisits::class)->visit($request->user(), $space);
        }

        // The member sees the post and its comments: the notifications about them are seen, not read (#1474)
        $user = $request->user();
        if ($user !== null) {
            app(MarksNotificationsSeen::class)->seenForPost($user, $post);
        }

        JsonResource::withoutWrapping();
        $comments = CommentThread::page($post, CommentThread::firstPage());

        // WARNING: The props passed here must remain aligned with the props expected by the page (Pages/Posts/Show.tsx)
        return Jetstream::inertia()->render($request, 'Posts/Show', [
            'community' => $community->toResource()->withLargeFields(),
            'space' => $space->toResource()->withLargeFields(),
            'spaceGroups' => SpaceTree::for($community, $request->user())->toResourceCollection(),
            'post' => $post->toResource()->withLargeFields(),
            'comments' => $comments['items']->toResourceCollection(),
            // The pagination of the comments (ADR 0073): the page loads the next ones
            'commentsPagination' => $comments['metadata']['pagination'],
            'permissions' => [
                'canManageSpaces' => Gate::check('manageSpaces', $community),
                'canContribute' => Gate::check('contribute', $space),
                'canModerate' => Gate::check('moderate', $space),
                'canUpdatePost' => Gate::check('update', $post),
                'canDeletePost' => Gate::check('delete', $post),
                // A moderator deletes the whole discussion, a marker too (#1565)
                'canDeletePostWithComments' => Gate::check('deleteWithComments', $post),
                'canPinPost' => Gate::check('pin', $post),
                'canComment' => Gate::check('comment', $post),
            ],
        ]);
    }
}
