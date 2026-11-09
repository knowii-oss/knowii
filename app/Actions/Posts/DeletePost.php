<?php

namespace App\Actions\Posts;

use App\Contracts\Posts\DeletesPosts;
use App\Events\Spaces\SpaceContentChanged;
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

        // Built before the delete: the event names the space of the post
        $event = SpaceContentChanged::forPost(SpaceContentChanged::POST_DELETED, $post, $user->cuid);
        $post->delete();
        event($event);
    }
}
