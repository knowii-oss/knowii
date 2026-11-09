<?php

namespace App\Actions\Comments;

use App\Constants;
use App\Contracts\Comments\CreatesComments;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateComment implements CreatesComments
{
    /**
     * Comment a post, or reply to a comment. A reply to a reply goes to the top-level comment (one level only).
     */
    final public function create(User $user, Post $post, array $input): Comment
    {
        Gate::forUser($user)->authorize('comment', $post);

        /** @var array{body: string, parent_cuid?: string|null} $validated */
        $validated = Validator::make($input, [
            // WARNING: aligned with commentFormSchema (comment.schema.ts)
            'body' => ['required', 'string', 'min:1', 'max:'.Constants::MAX_LENGTH_COMMENT_BODY],
            'parent_cuid' => ['sometimes', 'nullable', 'string'],
        ])->validate();

        $parentId = null;
        if (filled($validated['parent_cuid'] ?? null)) {
            $parent = $post->comments()->where('cuid', $validated['parent_cuid'])->first();
            if ($parent === null) {
                throw ValidationException::withMessages(['parent_cuid' => 'The comment to reply to does not exist on this post']);
            }
            $parentId = $parent->parent_id ?? $parent->id;
        }

        return DB::transaction(function () use ($post, $user, $parentId, $validated) {
            $comment = Comment::create([
                'community_id' => $post->community_id,
                'post_id' => $post->id,
                'author_id' => $user->id,
                'parent_id' => $parentId,
                'body' => $validated['body'],
                'body_html' => '',
                'edited_at' => null,
            ]);
            $comment->update(['body_html' => $comment->renderBodyAndSyncMentions($validated['body'], $user)]);

            return $comment;
        });
    }
}
