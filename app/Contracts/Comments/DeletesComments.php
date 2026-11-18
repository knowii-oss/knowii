<?php

namespace App\Contracts\Comments;

use App\Models\Comment;
use App\Models\User;

interface DeletesComments
{
    /**
     * Delete a comment (#1565, ADR 0050). Input: `delete_replies` (boolean, moderators only).
     *
     * @param  array<mixed>  $input
     * @return Comment|null The marker that stays for the replies of other people, or null when the comment is gone
     */
    public function delete(User $user, Comment $comment, array $input = []): ?Comment;
}
