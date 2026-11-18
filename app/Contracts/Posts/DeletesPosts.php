<?php

namespace App\Contracts\Posts;

use App\Models\Post;
use App\Models\User;

interface DeletesPosts
{
    /**
     * Delete a post (#1565, ADR 0050). Input: `delete_comments` (boolean, moderators only).
     *
     * @param  array<mixed>  $input
     * @return Post|null The marker that stays for the comments of other people, or null when the post is gone
     */
    public function delete(User $user, Post $post, array $input = []): ?Post;
}
