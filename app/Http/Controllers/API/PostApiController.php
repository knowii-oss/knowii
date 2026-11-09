<?php

namespace App\Http\Controllers\API;

use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\DeletesPosts;
use App\Contracts\Posts\PinsPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Community;
use App\Models\Post;
use App\Models\Space;
use App\Support\Pagination;
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
     * List the posts of a space: pinned first, then the newest. Offset pagination (ADR 0011).
     */
    #[QueryParameter('limit', description: 'Number of posts (default 10, maximum 100)', type: 'int', example: 10)]
    #[QueryParameter('offset', description: 'Number of posts to skip (default 0)', type: 'int', example: 0)]
    final public function index(Request $request, Community $community, Space $space): JsonResponse
    {
        Gate::authorize('view', $space);

        $page = Pagination::fromRequest($request)->apply($space->posts()->with(['author', 'space'])->forSpacePage());

        return self::success('Posts', $page['metadata'], PostResource::collection($page['items']));
    }

    /**
     * Write a post in a discussion space.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('title', description: 'Title (3 to 200 characters)', required: true, type: 'string', example: 'Welcome to the community')]
    #[BodyParameter('body', description: 'Body in Markdown (up to 50,000 characters). Raw HTML is escaped', required: true, type: 'string', example: 'Hello **everyone**!')]
    final public function store(Request $request, Community $community, Space $space): JsonResponse
    {
        $post = app(CreatesPosts::class)->create($request->user() ?? throw new AuthenticationException, $space, $request->only(['title', 'body']));

        return self::created(new PostResource($post->load(['author', 'space']), true), 'Post created successfully');
    }

    /**
     * Get a post, with its Markdown source.
     */
    final public function show(Community $community, Space $space, Post $post): JsonResponse
    {
        Gate::authorize('view', $post);

        return self::success('Post', null, new PostResource($post->load(['author', 'space']), true));
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

        return self::success('Post updated successfully', null, new PostResource($post->load(['author', 'space']), true));
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

        return self::success($post->is_pinned ? 'Post pinned' : 'Post unpinned', null, new PostResource($post->load(['author', 'space'])));
    }
}
