<?php

namespace App\Http\Controllers\API;

use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\DeletesPosts;
use App\Contracts\Posts\PinsPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Http\Controllers\Controller;
use App\Http\Resources\NewestPostResource;
use App\Models\Community;
use App\Models\Post;
use App\Models\Space;
use App\Support\NotificationFollowStates;
use App\Support\Pagination;
use App\Support\PublicCommunity;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Posts of discussion spaces (#1174). Changes are delegated to the actions (ADR 0006).
 */
class PostApiController extends Controller
{
    use ApiResponses;

    /**
     * List the posts of a space: pinned first, then the newest. The markers of deleted posts are not listed. Offset pagination (ADR 0011).
     */
    #[QueryParameter('limit', description: 'Number of posts (default 10, maximum 100)', type: 'int', example: 10)]
    #[QueryParameter('offset', description: 'Number of posts to skip (default 0)', type: 'int', example: 0)]
    final public function index(Request $request, Community $community, Space $space): JsonResponse
    {
        Gate::authorize('view', $space);

        $page = Pagination::fromRequest($request)->apply($space->posts()->listed()->with(['author', 'space', 'reactions'])->withCount('comments')->forSpacePage());
        // The follow state of the current member, for the whole page with one query (#1475)
        NotificationFollowStates::attach($request->user(), $page['items']);

        return self::success('Posts', $page['metadata'], $page['items']->toResourceCollection());
    }

    /**
     * The newest posts of the public discussion spaces of a community (10 at most), for its public page (#1485, ADR 0044):
     * the title, an excerpt, the author (name, username, avatar), the space and the date of each. Never the posts of
     * a space that is not public, of a chat space or of another community, nor the marker of a deleted post. A visitor
     * and a signed-in person who is not a member get it when the community has a public space that is not a chat space:
     * 404 to a visitor and 403 to the signed-in person otherwise. A member gets the same list.
     */
    final public function newest(Request $request, Community $community): JsonResponse
    {
        if (! PublicCommunity::isMember($request->user(), $community)) {
            PublicCommunity::ensureExists($community, $request);
        }

        return self::success('Newest posts', null, PublicCommunity::newestPosts($community)->toResourceCollection(NewestPostResource::class));
    }

    /**
     * Write a post in a discussion space. In a public space (`visibility` public), anyone on the internet can read the
     * post without an account, with the name, the username and the picture of its author.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('title', description: 'Title (3 to 200 characters). In a public space, anyone on the internet can read it, with the name and picture of the author', required: true, type: 'string', example: 'Welcome to the community')]
    #[BodyParameter('body', description: 'Body in Markdown (up to 50,000 characters). Raw HTML is escaped. In a public space, anyone on the internet can read it, with the name and picture of the author', required: true, type: 'string', example: 'Hello **everyone**!')]
    #[BodyParameter('notify_now', description: 'true: tell the members at Everything now, without the 5 minutes of waiting in which an edit or a deletion tells nobody. For the people who can moderate the space only (403 for the others). Default false', required: false, type: 'bool', example: false)]
    final public function store(Request $request, Community $community, Space $space): JsonResponse
    {
        $post = app(CreatesPosts::class)->create($request->user() ?? throw new AuthenticationException, $space, $request->only(['title', 'body', 'notify_now']));

        NotificationFollowStates::attach($request->user(), [$post]);

        return self::created($post->load(['author', 'space', 'reactions'])->toResource()->withLargeFields(), 'Post created successfully');
    }

    /**
     * Get a post, with its Markdown source.
     */
    final public function show(Request $request, Community $community, Space $space, Post $post): JsonResponse
    {
        Gate::authorize('view', $post);
        NotificationFollowStates::attach($request->user(), [$post]);

        return self::success('Post', null, $post->load(['author', 'space', 'reactions'])->toResource()->withLargeFields());
    }

    /**
     * Edit a post (author only).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('title', description: 'Title (3 to 200 characters)', required: false, type: 'string')]
    #[BodyParameter('body', description: 'Body in Markdown', required: false, type: 'string')]
    final public function update(Request $request, Community $community, Space $space, Post $post): JsonResponse
    {
        $post = app(UpdatesPosts::class)->update($request->user() ?? throw new AuthenticationException, $post, $request->only(['title', 'body']));

        NotificationFollowStates::attach($request->user(), [$post]);

        return self::success('Post updated successfully', null, $post->load(['author', 'space', 'reactions'])->toResource()->withLargeFields());
    }

    /**
     * Delete a post (author or moderator).
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, Space $space, Post $post): Response
    {
        app(DeletesPosts::class)->delete($request->user() ?? throw new AuthenticationException, $post);

        return self::deleted();
    }

    /**
     * Pin or unpin a post (moderators).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('pinned', description: 'true to pin the post at the top of the space, false to unpin it', required: true, type: 'bool')]
    final public function pin(Request $request, Community $community, Space $space, Post $post): JsonResponse
    {
        $post = app(PinsPosts::class)->pin($request->user() ?? throw new AuthenticationException, $post, $request->only(['pinned']));

        NotificationFollowStates::attach($request->user(), [$post]);

        return self::success($post->is_pinned ? 'Post pinned' : 'Post unpinned', null, $post->load(['author', 'space', 'reactions'])->toResource());
    }
}
