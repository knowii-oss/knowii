<?php

namespace App\Contracts\Comments;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;

interface CreatesComments
{
    /**
     * @param  array<mixed>  $input
     */
    public function create(User $user, Post $post, array $input): Comment;
}
