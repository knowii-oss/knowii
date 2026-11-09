<?php

namespace App\Actions\Posts;

use App\Contracts\Posts\DeletesPosts;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class DeletePost implements DeletesPosts
{
    /**
     * The author or a moderator deletes a post (and its comments, reactions and mentions: cascade).
     */
    final public function delete(User $user, Post $post): void
    {
        Log::info('Processing request to delete a post', ['post' => $post->cuid, 'by' => $user->cuid]);

        Gate::forUser($user)->authorize('delete', $post);

        $post->delete();
    }
}
