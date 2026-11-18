<?php

namespace App\Actions\Comments;

use App\Contracts\Comments\DeletesComments;
use App\Contracts\Content\LeavesMarkers;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * The author or a moderator deletes a comment (#1565, ADR 0050). A comment that has a reply of other people (not a
 * marker, not written by the person who deletes, a deleted account included) stays as a marker, with every reply: the
 * same marker as the account deletion (`LeavesMarkers`, ADR 0043). Any other comment goes with its replies (cascade).
 *
 * With `delete_replies`, a person who moderates the space deletes the comment and every reply under it, a marker too
 * (`CommentPolicy::deleteWithReplies`). A marker whose last reply goes stays, like a chat message (#1185): nobody
 * deletes it again without `delete_replies`.
 */
class DeleteComment implements DeletesComments
{
    final public function delete(User $user, Comment $comment, array $input = []): ?Comment
    {
        Log::info('Processing request to delete a comment', ['comment' => $comment->cuid, 'by' => $user->cuid]);

        $gate = Gate::forUser($user);
        if ($gate->none(['delete', 'deleteWithReplies'], $comment)) {
            throw new AuthorizationException;
        }

        /** @var array{delete_replies?: bool|int|string} $validated */
        $validated = Validator::make($input, ['delete_replies' => ['sometimes', 'boolean']])->validate();
        $withReplies = filter_var($validated['delete_replies'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $gate->authorize($withReplies ? 'deleteWithReplies' : 'delete', $comment);

        // Built before the delete: the event names the subject of the comment. A marker is an update of the comment: the
        // open pages load the comments again and show it
        $updated = SpaceContentChanged::forComment(SpaceContentChanged::COMMENT_UPDATED, $comment, $user->cuid);
        $deleted = SpaceContentChanged::forComment(SpaceContentChanged::COMMENT_DELETED, $comment, $user->cuid);

        $marker = DB::transaction(static function () use ($user, $comment, $withReplies): bool {
            $markers = app(LeavesMarkers::class);
            if (! $withReplies && $markers->commentsAnsweredByOthers([$comment->id], $user) !== []) {
                $markers->markComments([$comment->id]);

                return true;
            }

            $comment->delete();

            return false;
        });

        event($marker ? $updated : $deleted);
        Log::info($marker ? 'Comment deleted, its marker stays' : 'Comment deleted', ['comment' => $comment->cuid]);

        return $marker ? $comment->refresh() : null;
    }
}
