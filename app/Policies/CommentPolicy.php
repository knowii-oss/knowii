<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Gate;

/**
 * Who can read and change a comment (#1175). Writing a comment is PostPolicy::comment.
 */
class CommentPolicy
{
    use HandlesAuthorization;

    final public function view(?User $user, Comment $comment): bool
    {
        return Gate::forUser($user)->check('view', $comment->post);
    }

    final public function update(User $user, Comment $comment): bool
    {
        return $comment->author_id === $user->id && $user->belongsToCommunity($comment->community);
    }

    final public function delete(User $user, Comment $comment): bool
    {
        return $this->update($user, $comment) || Gate::forUser($user)->check('moderate', $comment->post->space);
    }
}
