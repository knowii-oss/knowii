<?php

namespace App\Contracts\Posts;

use App\Models\Post;
use App\Models\User;

interface PinsPosts
{
    /**
     * @param  array<mixed>  $input
     */
    public function pin(User $user, Post $post, array $input): Post;
}
