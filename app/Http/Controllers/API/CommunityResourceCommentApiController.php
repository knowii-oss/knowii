<?php

namespace App\Http\Controllers\API;

use App\Contracts\Comments\CreatesComments;
use App\Contracts\Comments\DeletesComments;
use App\Contracts\Comments\UpdatesComments;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\Space;
use App\Support\CommentThread;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Comments on the resources of resources spaces, with one level of replies (#1034). Same actions as the comments on
 * posts (ADR 0006).
 */
class CommunityResourceCommentApiController extends Controller
{
    use ApiResponses;

    /**
     * The comments of a resource, oldest first, each with its replies (oldest first).
     */
    final public function index(Community $community, Space $space, CommunityResource $resource): JsonResponse
    {
        Gate::authorize('view', $resource);

        return self::success('Comments', ['totalCount' => $resource->comments()->count()], CommentThread::for($resource)->toResourceCollection());
    }

    /**
     * Comment a resource, or reply to a comment (parent_cuid).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('body', description: 'Comment in Markdown (up to 10,000 characters)', required: true, type: 'string', example: 'Worth reading!')]
    #[BodyParameter('parent_cuid', description: 'The comment to reply to. A reply to a reply goes to its top-level comment (one level only)', required: false, type: 'string')]
    final public function store(Request $request, Community $community, Space $space, CommunityResource $resource): JsonResponse
    {
        $comment = app(CreatesComments::class)->create($request->user() ?? throw new AuthenticationException, $resource, $request->only(['body', 'parent_cuid']));

        return self::created($comment->load(['author', 'parent', 'reactions'])->toResource(), 'Comment published');
    }

    /**
     * Edit a comment (author only).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('body', description: 'Comment in Markdown', required: true, type: 'string')]
    final public function update(Request $request, Community $community, Space $space, CommunityResource $resource, Comment $comment): JsonResponse
    {
        $comment = app(UpdatesComments::class)->update($request->user() ?? throw new AuthenticationException, $comment, $request->only(['body']));

        return self::success('Comment updated', null, $comment->load(['author', 'parent', 'reactions'])->toResource());
    }

    /**
     * Delete a comment and its replies (author or moderator).
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, Space $space, CommunityResource $resource, Comment $comment): Response
    {
        app(DeletesComments::class)->delete($request->user() ?? throw new AuthenticationException, $comment);

        return self::deleted();
    }
}
