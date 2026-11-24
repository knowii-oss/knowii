<?php

namespace App\Http\Controllers\API;

use App\Contracts\Comments\CreatesComments;
use App\Contracts\Comments\DeletesComments;
use App\Contracts\Comments\UpdatesComments;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Post;
use App\Models\Space;
use App\Support\CommentThread;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
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
     * Paged with `limit` and `offset` (at most 100 comments in one answer, the replies counted, default 100): a page that
     * starts inside a thread has its top-level comment again, with the next replies only. `metadata.totalCount` counts
     * every comment of the item.
     */
    #[QueryParameter('limit', description: 'Comments per answer, the replies counted (1 to 100, default 100)', type: 'int', example: 100)]
    #[QueryParameter('offset', description: 'Comments to skip, the replies counted (default 0)', type: 'int', example: 0)]
    final public function index(Request $request, Community $community, Space $space, Post $post): JsonResponse
    {
        Gate::authorize('view', $post);

        $page = CommentThread::page($post, CommentThread::pagination($request));

        return self::success('Comments', $page['metadata'], $page['items']->toResourceCollection());
    }

    /**
     * Comment a post, or reply to a comment (parent_cuid). In a public space (`visibility` public), anyone on the
     * internet can read the comment without an account, with the name, the username and the picture of its author.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('body', description: 'Comment in Markdown (up to 10,000 characters). In a public space, anyone on the internet can read it, with the name and picture of the author', required: true, type: 'string', example: 'Great post!')]
    #[BodyParameter('parent_cuid', description: 'The comment to reply to. A reply to a reply goes to its top-level comment (one level only)', required: false, type: 'string')]
    final public function store(Request $request, Community $community, Space $space, Post $post): JsonResponse
    {
        $comment = app(CreatesComments::class)->create($request->user() ?? throw new AuthenticationException, $post, $request->only(['body', 'parent_cuid']));

        return self::created($comment->load(['author', 'parent', 'reactions'])->toResource(), 'Comment published');
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

        return self::success('Comment updated', null, $comment->load(['author', 'parent', 'reactions'])->toResource());
    }

    /**
     * Delete a comment (author or moderator, #1565). A comment that has replies of other people stays as a marker, with
     * every reply: the answer is 200, and the list shows "This comment was deleted." in its place. Any other comment goes
     * with its replies: the answer is 204. `delete_replies` (moderators only, 403 for the others) deletes the comment and
     * every reply under it, a marker too. A marker cannot be deleted again without it (403).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('delete_replies', description: 'Also delete every reply, the replies of other people too (moderators of the space only). Without it, a comment that has replies of other people stays as a marker', required: false, type: 'bool', example: false)]
    final public function destroy(Request $request, Community $community, Space $space, Post $post, Comment $comment): JsonResponse|Response
    {
        $marker = app(DeletesComments::class)->delete($request->user() ?? throw new AuthenticationException, $comment, $request->only(['delete_replies']));

        return $marker === null ? self::deleted() : self::success('Comment deleted, its replies stay');
    }
}
