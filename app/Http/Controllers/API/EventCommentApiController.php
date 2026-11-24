<?php

namespace App\Http\Controllers\API;

use App\Actions\Events\EventPublication;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Comments\DeletesComments;
use App\Contracts\Comments\UpdatesComments;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Event;
use App\Models\Space;
use App\Support\CommentThread;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Comments on the events of events spaces, with one level of replies (#1516, ADR 0053): the comments of posts and
 * resources, with the same actions (ADR 0006). A space that the person cannot read answers first (`SpaceAccess`: 404 for
 * a person who is not a member, 403 for a member). Every route then answers as the other routes of an event: 404 for an
 * event that the person may not see (a draft of another host), then 403 for a person who is not a member. A draft takes
 * no comment (422). There is no reaction on the event itself.
 */
class EventCommentApiController extends Controller
{
    use ApiResponses;

    /**
     * The comments of an event, oldest first, each with its replies (oldest first). A visitor reads the comments of a
     * published or cancelled event of a public space (404 elsewhere, and for a draft), as the event itself.
     * Paged with `limit` and `offset` (at most 100 comments in one answer, the replies counted, default 100): a page that
     * starts inside a thread has its top-level comment again, with the next replies only. `metadata.totalCount` counts
     * every comment of the item.
     */
    #[QueryParameter('limit', description: 'Comments per answer, the replies counted (1 to 100, default 100)', type: 'int', example: 100)]
    #[QueryParameter('offset', description: 'Comments to skip, the replies counted (default 0)', type: 'int', example: 0)]
    final public function index(Request $request, Community $community, Space $space, Event $event): JsonResponse
    {
        self::inSpace($community, $space, $event);
        // A reader who cannot see the event, a visitor too: the answer of an event that does not exist
        EventPublication::assertVisible($request->user(), $event);

        $page = CommentThread::page($event, CommentThread::pagination($request));

        return self::success('Comments', $page['metadata'], $page['items']->toResourceCollection());
    }

    /**
     * Comment an event, or reply to a comment (parent_cuid): the members of the community who can see the event, on a
     * published, cancelled or past event. A draft takes no comment (422). In a public space (`visibility` public), anyone
     * on the internet can read the comment without an account, with the name, the username and the picture of its author.
     * A first-level comment tells the host of the event.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('body', description: 'Comment in Markdown (up to 10,000 characters). In a public space, anyone on the internet can read it, with the name and picture of the author', required: true, type: 'string', example: 'Thank you for the session!')]
    #[BodyParameter('parent_cuid', description: 'The comment to reply to. A reply to a reply goes to its top-level comment (one level only)', required: false, type: 'string')]
    final public function store(Request $request, Community $community, Space $space, Event $event): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        self::inSpace($community, $space, $event);
        $comment = app(CreatesComments::class)->create($user, $event, $request->only(['body', 'parent_cuid']));

        return self::created($comment->load(['author', 'parent', 'reactions'])->toResource(), 'Comment published');
    }

    /**
     * Edit a comment (author only, while a member who can read the space).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('body', description: 'Comment in Markdown', required: true, type: 'string')]
    final public function update(Request $request, Community $community, Space $space, Event $event, Comment $comment): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;
        self::ofEvent($community, $space, $event, $comment);
        $comment = app(UpdatesComments::class)->update($user, $comment, $request->only(['body']));

        return self::success('Comment updated', null, $comment->load(['author', 'parent', 'reactions'])->toResource());
    }

    /**
     * Delete a comment (author or moderator). A comment that has replies of other people stays as a marker, with every
     * reply: the answer is 200, and the list shows "This comment was deleted." in its place. Any other comment goes with
     * its replies: the answer is 204. `delete_replies` (moderators only, 403 for the others) deletes the comment and every
     * reply under it, a marker too. A marker cannot be deleted again without it (403).
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('delete_replies', description: 'Also delete every reply, the replies of other people too (moderators of the space only). Without it, a comment that has replies of other people stays as a marker', required: false, type: 'bool', example: false)]
    final public function destroy(Request $request, Community $community, Space $space, Event $event, Comment $comment): JsonResponse|Response
    {
        $user = $request->user() ?? throw new AuthenticationException;
        self::ofEvent($community, $space, $event, $comment);
        $marker = app(DeletesComments::class)->delete($user, $comment, $request->only(['delete_replies']));

        return $marker === null ? self::deleted() : self::success('Comment deleted, its replies stay');
    }

    /**
     * The policies read the space and the community of the event: they are known
     */
    private static function inSpace(Community $community, Space $space, Event $event): void
    {
        $space->setRelation('community', $community);
        $event->setRelation('space', $space);
        $event->setRelation('community', $community);
    }

    /**
     * The comment of this event (the scoped binding), with its event
     */
    private static function ofEvent(Community $community, Space $space, Event $event, Comment $comment): void
    {
        self::inSpace($community, $space, $event);
        $comment->setRelation('event', $event);
    }
}
