<?php

namespace App\Contracts\Posts;

use App\Models\Post;
use App\Models\Space;
use App\Models\User;

interface CreatesPosts
{
    /**
     * @param  array<mixed>  $input
     */
    public function create(User $user, Space $space, array $input): Post;
}
