<?php

namespace App\Actions\Comments;

use App\Actions\Events\EventComments;
use App\Constants;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Notifications\FollowsThreads;
use App\Contracts\Notifications\Notifies;
use App\Contracts\Notifications\TracksSpaceVisits;
use App\Enums\KnowiiFollowReason;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\Comment;
use App\Models\CommunityResource;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use App\Rules\MaxMarkdownImages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateComment implements CreatesComments
{
    /**
     * Comment a post, a resource (#1034) or an event (#1516), or reply to a comment. A reply to a reply goes to the
     * top-level comment (one level only).
     *
     * An event first gives the answers of its other routes (EventComments): 404 for a person who may not see it, 403 for
     * a person who is not a member, 422 for a draft. The comment then waits for a change of state of the event that runs
     * (SetEventState holds the lock of the row): a draft never holds a comment.
     */
    final public function create(User $user, Post|CommunityResource|Event $subject, array $input): Comment
    {
        if ($subject instanceof Event) {
            EventComments::assertAllowed($user, $subject, true);
        }
        Gate::forUser($user)->authorize('comment', $subject);

        /** @var array{body: string, parent_cuid?: string|null} $validated */
        $validated = Validator::make($input, [
            // WARNING: aligned with commentFormSchema (comment.schema.ts)
            'body' => ['required', 'string', 'min:1', 'max:'.Constants::MAX_LENGTH_COMMENT_BODY, new MaxMarkdownImages],
            'parent_cuid' => ['sometimes', 'nullable', 'string'],
        ])->validate();

        $parentId = null;
        if (filled($validated['parent_cuid'] ?? null)) {
            $parent = $subject->comments()->where('cuid', $validated['parent_cuid'])->first();
            if ($parent === null) {
                throw ValidationException::withMessages(['parent_cuid' => 'The comment to reply to does not exist here']);
            }
            $parentId = $parent->parent_id ?? $parent->id;
        }

        $comment = DB::transaction(function () use ($subject, $user, $parentId, $validated) {
            if ($subject instanceof Event) {
                // A shared lock: comments do not wait for each other, a change of state waits for them, and they for it.
                // The state of the row as it is now decides
                EventComments::assertNotDraft(Event::query()->whereKey($subject->id)->sharedLock()->firstOrFail(['id', 'state']));
            }
            $comment = Comment::create([
                'community_id' => $subject->community_id,
                'post_id' => $subject instanceof Post ? $subject->id : null,
                'community_resource_id' => $subject instanceof CommunityResource ? $subject->id : null,
                'event_id' => $subject instanceof Event ? $subject->id : null,
                'author_id' => $user->id,
                'parent_id' => $parentId,
                'body' => $validated['body'],
                'body_html' => '',
                'edited_at' => null,
            ]);
            $comment->update(['body_html' => $comment->renderBodyAndSyncMentions($validated['body'], $user)]);
            $comment->syncUploadUsages($validated['body']);
            // Live updates (#1180): sent after the commit (ShouldDispatchAfterCommit)
            event(SpaceContentChanged::forComment(SpaceContentChanged::COMMENT_CREATED, $comment, $user->cuid));

            return $comment;
        });

        // The comment of a post is new content of its space, the one of a resource or of an event only a visit (#1476)
        app(TracksSpaceVisits::class)->wroteIn($user, $subject->space, $subject instanceof Post);
        // A member who comments a post follows its thread, unless they stopped following it or muted it (#1475)
        if ($subject instanceof Post) {
            app(FollowsThreads::class)->followByWriting($user, $subject, KnowiiFollowReason::Replied);
        }
        // Tell the members who are concerned (#1474, #1475): queued jobs, after the commit. Never from an edit
        app(Notifies::class)->notify($comment);

        return $comment;
    }
}
