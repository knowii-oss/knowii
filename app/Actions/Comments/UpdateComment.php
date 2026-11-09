<?php

namespace App\Actions\Comments;

use App\Constants;
use App\Contracts\Comments\UpdatesComments;
use App\Models\Comment;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateComment implements UpdatesComments
{
    /**
     * The author edits the text of a comment.
     */
    final public function update(User $user, Comment $comment, array $input): Comment
    {
        Gate::forUser($user)->authorize('update', $comment);

        /** @var array{body: string} $validated */
        $validated = Validator::make($input, [
            'body' => ['required', 'string', 'min:1', 'max:'.Constants::MAX_LENGTH_COMMENT_BODY],
        ])->validate();

        $comment->update([
            'body' => $validated['body'],
            'body_html' => Markdown::render($validated['body']),
            'edited_at' => now(),
        ]);

        return $comment;
    }
}
