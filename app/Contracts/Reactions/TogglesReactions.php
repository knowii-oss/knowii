<?php

namespace App\Contracts\Reactions;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;

interface TogglesReactions
{
    /**
     * @param  array<mixed>  $input
     * @return list<array{kind: string, count: int, reacted: bool}>
     */
    public function toggle(User $user, Post|Comment $reactable, array $input): array;
}
