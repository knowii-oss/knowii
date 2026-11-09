<?php

namespace App\Contracts\Posts;

use App\Models\Post;
use App\Models\User;

interface UpdatesPosts
{
    /**
     * @param  array<mixed>  $input
     */
    public function update(User $user, Post $post, array $input): Post;
}
