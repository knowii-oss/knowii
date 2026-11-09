<?php

namespace App\Support;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;

/**
 * The comments of a post as shown on the page: top-level comments, oldest first, each with its replies.
 */
final class CommentThread
{
    /**
     * Upper bound of the top-level comments returned at once (a post with more is rare; pagination can come later).
     */
    public const int MAX_TOP_LEVEL_COMMENTS = 500;

    /**
     * @return Collection<int, Comment>
     */
    public static function for(Post $post): Collection
    {
        return $post->comments()
            ->whereNull('parent_id')
            ->with(['author', 'reactions', 'replies.author', 'replies.reactions'])
            ->limit(self::MAX_TOP_LEVEL_COMMENTS)
            ->get();
    }
}
