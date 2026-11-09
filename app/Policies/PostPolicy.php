<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Gate;

/**
 * Who can read and change a post (#1174). Writing a new post is SpacePolicy::contribute.
 */
class PostPolicy
{
    use HandlesAuthorization;

    final public function view(?User $user, Post $post): bool
    {
        return Gate::forUser($user)->check('view', $post->space);
    }

    /**
     * Only the author edits a post, while they are still a member of the community.
     */
    final public function update(User $user, Post $post): bool
    {
        return $post->author_id === $user->id && $user->belongsToCommunity($post->community);
    }

    /**
     * The author, or a moderator of the space.
     */
    final public function delete(User $user, Post $post): bool
    {
        return $this->update($user, $post) || Gate::forUser($user)->check('moderate', $post->space);
    }

    final public function pin(User $user, Post $post): bool
    {
        return Gate::forUser($user)->check('moderate', $post->space);
    }
}
