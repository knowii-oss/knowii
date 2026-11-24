<?php

namespace App\Support;

use App\Constants;
use App\Models\Comment;
use App\Models\CommunityResource;
use App\Models\Event;
use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * The comments of a post, a resource (#1034) or an event (#1516) as shown on the page: top-level comments, oldest first, each with its
 * replies, oldest first.
 *
 * The list is paged (ADR 0073): one page holds at most `limit` comments, the replies counted, in the order of the page
 * (each thread, then the next one). A page that starts inside a thread has that top-level comment again, with the next
 * replies only: the client adds them to the thread that it already shows.
 */
final class CommentThread
{
    /**
     * The page of the request (`limit` and `offset`): at most Constants::COMMENTS_PER_PAGE comments
     */
    public static function pagination(Request $request): Pagination
    {
        return Pagination::fromRequest($request, Constants::COMMENTS_PER_PAGE, Constants::COMMENTS_PER_PAGE);
    }

    /**
     * The first page, for the page of the item
     */
    public static function firstPage(): Pagination
    {
        return new Pagination(Constants::COMMENTS_PER_PAGE, 0);
    }

    /**
     * @return array{items: Collection<int, Comment>, metadata: array{totalCount: int, pagination: array<string, int|null>}}
     */
    public static function page(Post|CommunityResource|Event $subject, Pagination $pagination): array
    {
        $total = $subject->comments()->count();

        // The order of the page: the threads oldest first, and in each thread its top-level comment, then its replies
        // oldest first
        /** @var Collection<int, Comment> $rows */
        $rows = $subject->comments()
            ->reorder()
            ->leftJoin('comments as threads', 'threads.id', '=', 'comments.parent_id')
            ->select('comments.*')
            ->orderByRaw('COALESCE(threads.created_at, comments.created_at), COALESCE(threads.id, comments.id), comments.parent_id IS NOT NULL, comments.created_at, comments.id')
            ->skip($pagination->offset)
            ->take($pagination->limit)
            ->with(['author', 'reactions'])
            ->get();

        /** @var array<int, Comment> $threads */
        $threads = [];
        /** @var array<int, list<Comment>> $replies */
        $replies = [];
        foreach ($rows as $row) {
            $threadId = $row->parent_id ?? $row->id;
            if ($row->parent_id === null) {
                $threads[$threadId] = $row;
            }
            $replies[$threadId] ??= [];
            if ($row->parent_id !== null) {
                $replies[$threadId][] = $row;
            }
        }

        // The page starts inside a thread: its top-level comment comes again, first
        $missing = array_values(array_diff(array_keys($replies), array_keys($threads)));
        if ($missing !== []) {
            foreach (Comment::query()->with(['author', 'reactions'])->findMany($missing) as $parent) {
                $threads[$parent->id] = $parent;
            }
        }

        $items = new Collection;
        foreach ($replies as $threadId => $threadReplies) {
            $thread = $threads[$threadId] ?? null;
            if ($thread === null) {
                continue;
            }
            foreach ($threadReplies as $reply) {
                $reply->setRelation('parent', $thread);
            }
            $thread->setRelation('replies', new Collection($threadReplies));
            $items->push($thread);
        }

        return ['items' => $items, 'metadata' => ['totalCount' => $total, 'pagination' => $pagination->metadata($total)]];
    }
}
