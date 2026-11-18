<?php

namespace App\Actions\Content;

use App\Contracts\Content\LeavesMarkers;
use App\Contracts\Notifications\PrunesNotifications;
use App\Models\Comment;
use App\Models\Mention;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\UploadUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Turns posts and comments into markers (#1565, ADR 0050), for `DeleteComment`, `DeletePost` and `DeleteMemberContent`
 * (ADR 0043). It writes with the query builder (no model event, so the slug of a post is not made again from its empty
 * title) and checks no permission: the caller did.
 */
final class LeaveMarkers implements LeavesMarkers
{
    final public function postsAnsweredByOthers(array $postIds, User $user): array
    {
        if ($postIds === []) {
            return [];
        }

        return self::parentsAnsweredByOthers(Comment::query()->whereIn('post_id', $postIds), 'post_id', $user);
    }

    final public function commentsAnsweredByOthers(array $commentIds, User $user): array
    {
        if ($commentIds === []) {
            return [];
        }

        return self::parentsAnsweredByOthers(Comment::query()->whereIn('parent_id', $commentIds), 'parent_id', $user);
    }

    final public function markPosts(array $postIds): void
    {
        if ($postIds === []) {
            return;
        }

        app(PrunesNotifications::class)->forPosts($postIds);
        Post::query()->whereIn('id', $postIds)->update([
            'title' => '',
            // The slug holds the old title: the marker gets one that names nothing
            'slug' => DB::raw("'deleted-' || lower(cuid)"),
            'body' => '',
            'body_html' => '',
            'author_id' => null,
            'is_pinned' => false,
            'pinned_at' => null,
            'edited_at' => null,
            'deleted_at' => now(),
        ]);
        self::clearItems('post_id', $postIds);
    }

    final public function markComments(array $commentIds): void
    {
        if ($commentIds === []) {
            return;
        }

        app(PrunesNotifications::class)->forComments($commentIds);
        Comment::query()->whereIn('id', $commentIds)->update([
            'body' => '',
            'body_html' => '',
            'author_id' => null,
            'edited_at' => null,
            'deleted_at' => now(),
        ]);
        self::clearItems('comment_id', $commentIds);
    }

    /**
     * The parents (posts or comments) of the comments of the query that other people than $user wrote, markers left out
     *
     * @param  Builder<Comment>  $comments
     * @param  'post_id'|'parent_id'  $column
     * @return list<int>
     */
    private static function parentsAnsweredByOthers(Builder $comments, string $column, User $user): array
    {
        return array_values($comments
            ->whereNull('deleted_at')
            ->where(static fn (Builder $query) => $query->whereNull('author_id')->orWhere('author_id', '!=', $user->id))
            ->select($column)
            ->distinct()
            ->get()
            ->map(static fn (Comment $comment): int => (int) ($column === 'post_id' ? $comment->post_id : $comment->parent_id))
            ->all());
    }

    /**
     * The reactions, the mentions and the upload usages of items that became markers (the cascades only cover the rows
     * that go).
     *
     * @param  'post_id'|'comment_id'  $column
     * @param  list<int>  $ids
     */
    private static function clearItems(string $column, array $ids): void
    {
        Reaction::query()->whereIn($column, $ids)->delete();
        Mention::query()->whereIn($column, $ids)->delete();
        UploadUsage::query()->whereIn($column, $ids)->delete();
    }
}
