<?php

namespace App\Actions\Comments;

use App\Actions\Events\EventComments;
use App\Constants;
use App\Contracts\Comments\UpdatesComments;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\Comment;
use App\Models\User;
use App\Rules\MaxMarkdownImages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateComment implements UpdatesComments
{
    /**
     * The author edits the text of a comment.
     */
    final public function update(User $user, Comment $comment, array $input): Comment
    {
        // A comment of an event gives the answers of the other routes of the event first (#1516)
        if ($comment->event_id !== null && $comment->event !== null) {
            EventComments::assertAllowed($user, $comment->event, false);
        }
        Gate::forUser($user)->authorize('update', $comment);

        /** @var array{body: string} $validated */
        $validated = Validator::make($input, [
            'body' => ['required', 'string', 'min:1', 'max:'.Constants::MAX_LENGTH_COMMENT_BODY, new MaxMarkdownImages],
        ])->validate();

        DB::transaction(function () use ($comment, $validated, $user) {
            $comment->update([
                'body' => $validated['body'],
                'body_html' => $comment->renderBodyAndSyncMentions($validated['body'], $comment->author),
                'edited_at' => now(),
            ]);
            $comment->syncUploadUsages($validated['body']);
            event(SpaceContentChanged::forComment(SpaceContentChanged::COMMENT_UPDATED, $comment, $user->cuid));
        });

        return $comment;
    }
}
