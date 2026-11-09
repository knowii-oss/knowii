<?php

namespace App\Contracts\Comments;

use App\Models\Comment;
use App\Models\User;

interface DeletesComments
{
    public function delete(User $user, Comment $comment): void;
}
