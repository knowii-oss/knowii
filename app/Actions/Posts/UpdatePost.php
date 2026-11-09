<?php

namespace App\Actions\Posts;

use App\Contracts\Posts\UpdatesPosts;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UpdatePost implements UpdatesPosts
{
    /**
     * The author edits the title or the body. The slug stays, so links keep working.
     */
    final public function update(User $user, Post $post, array $input): Post
    {
        Log::info('Processing request to update a post', ['post' => $post->cuid]);

        Gate::forUser($user)->authorize('update', $post);

        /** @var array{title?: string, body?: string} $validated */
        $validated = Validator::make($input, CreatePost::rules(false))->validate();

        $changes = [];
        if (array_key_exists('title', $validated)) {
            $changes['title'] = trim($validated['title']);
        }
        if (array_key_exists('body', $validated)) {
            $changes['body'] = $validated['body'];
            $changes['body_html'] = $post->renderBodyAndSyncMentions($validated['body'], $post->author);
        }

        if ($changes !== []) {
            DB::transaction(fn () => $post->update([...$changes, 'edited_at' => now()]));
        }

        return $post;
    }
}
