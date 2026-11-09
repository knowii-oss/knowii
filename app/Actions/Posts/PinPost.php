<?php

namespace App\Actions\Posts;

use App\Contracts\Posts\PinsPosts;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class PinPost implements PinsPosts
{
    /**
     * Moderators pin a post at the top of its space, or unpin it.
     */
    final public function pin(User $user, Post $post, array $input): Post
    {
        Gate::forUser($user)->authorize('pin', $post);

        /** @var array{pinned: bool} $validated */
        $validated = Validator::make($input, ['pinned' => ['required', 'boolean']])->validate();

        $post->update([
            'is_pinned' => $validated['pinned'],
            'pinned_at' => $validated['pinned'] ? now() : null,
        ]);

        return $post;
    }
}
