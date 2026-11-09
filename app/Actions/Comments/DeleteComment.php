<?php

namespace App\Actions\Comments;

use App\Contracts\Comments\DeletesComments;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class DeleteComment implements DeletesComments
{
    /**
     * The author or a moderator deletes a comment. Its replies are deleted with it (cascade).
     */
    final public function delete(User $user, Comment $comment): void
    {
        Log::info('Processing request to delete a comment', ['comment' => $comment->cuid, 'by' => $user->cuid]);

        Gate::forUser($user)->authorize('delete', $comment);

        $comment->delete();
    }
}
