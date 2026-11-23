<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Gate;

/**
 * Who can read and change a comment (#1175). Writing a comment is PostPolicy::comment,
 * CommunityResourcePolicy::comment (#1034) or EventPolicy::comment (#1516). Editing or deleting a comment needs the
 * access to its space (SpacePolicy::view), for the comments of posts, resources and events alike.
 */
class CommentPolicy
{
    use HandlesAuthorization;

    final public function view(?User $user, Comment $comment): bool
    {
        return Gate::forUser($user)->check('view', $comment->subject());
    }

    /**
     * Members who can comment the post, the resource or the event can react to its comments.
     */
    final public function react(User $user, Comment $comment): bool
    {
        return ! $comment->isDeleted() && Gate::forUser($user)->check('comment', $comment->subject());
    }

    /**
     * The author, while still a member of the community who can read the space of the comment.
     */
    final public function update(User $user, Comment $comment): bool
    {
        return ! $comment->isDeleted()
            && $comment->author_id === $user->id && $user->belongsToCommunity($comment->community)
            && Gate::forUser($user)->check('view', $comment->subject()->space);
    }

    /**
     * The author (who can read the space), or a moderator of the space (SpacePolicy::moderate reads the space too). A
     * marker is never deleted again this way, like a chat message (#1185): only with its replies (deleteWithReplies).
     */
    final public function delete(User $user, Comment $comment): bool
    {
        return ! $comment->isDeleted()
            && ($this->update($user, $comment) || Gate::forUser($user)->check('moderate', $comment->subject()->space));
    }

    /**
     * Delete the comment with every reply under it, the replies of other people too (#1565, ADR 0050): a moderator of
     * the space only, a marker included. The author alone leaves a marker for the replies of other people.
     */
    final public function deleteWithReplies(User $user, Comment $comment): bool
    {
        return Gate::forUser($user)->check('moderate', $comment->subject()->space);
    }
}
