<?php

namespace App\Contracts\Comments;

use App\Models\Comment;
use App\Models\User;

interface UpdatesComments
{
    /**
     * @param  array<mixed>  $input
     */
    public function update(User $user, Comment $comment, array $input): Comment;
}
