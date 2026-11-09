<?php

namespace App\Contracts\Posts;

use App\Models\Post;
use App\Models\User;

interface DeletesPosts
{
    public function delete(User $user, Post $post): void;
}
