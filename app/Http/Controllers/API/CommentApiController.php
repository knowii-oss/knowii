<?php

namespace App\Http\Controllers\API;

use App\Contracts\Comments\CreatesComments;
use App\Contracts\Comments\DeletesComments;
use App\Contracts\Comments\UpdatesComments;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Post;
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
 * Comments on posts, with one level of replies (#1175). Changes are delegated to the actions (ADR 0006).
 */
class CommentApiController extends Controller
{
    use ApiResponses;

    /**
     * The comments of a post, oldest first, each with its replies (oldest first).
     */
    final public function index(Community $community, Space $space, Post $post): JsonResponse
    {
        Gate::authorize('view', $post);

        $comments = CommentThread::for($post);

        return self::success('Comments', ['totalCount' => $post->comments()->count()], CommentResource::collection($comments));
    }

    /**
     * Comment a post, or reply to a comment (parent_cuid).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('body', description: 'Comment in Markdown (up to 10,000 characters)', required: true, type: 'string', example: 'Great post!')]
    #[BodyParameter('parent_cuid', description: 'The comment to reply to. A reply to a reply goes to its top-level comment (one level only)', required: false, type: 'string')]
    final public function store(Request $request, Community $community, Space $space, Post $post): JsonResponse
    {
        $comment = app(CreatesComments::class)->create($request->user() ?? throw new AuthenticationException, $post, $request->only(['body', 'parent_cuid']));

        return self::created(new CommentResource($comment->load(['author', 'parent', 'reactions'])), 'Comment published');
    }

    /**
     * Edit a comment (author only).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('body', description: 'Comment in Markdown', required: true, type: 'string')]
    final public function update(Request $request, Community $community, Space $space, Post $post, Comment $comment): JsonResponse
    {
        $comment = app(UpdatesComments::class)->update($request->user() ?? throw new AuthenticationException, $comment, $request->only(['body']));

        return self::success('Comment updated', null, new CommentResource($comment->load(['author', 'parent', 'reactions'])));
    }

    /**
     * Delete a comment and its replies (author or moderator).
     *
     * @throws AuthenticationException
     */
    final public function destroy(Request $request, Community $community, Space $space, Post $post, Comment $comment): Response
    {
        app(DeletesComments::class)->delete($request->user() ?? throw new AuthenticationException, $comment);

        return self::deleted();
    }
}
