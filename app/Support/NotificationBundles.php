<?php

namespace App\Support;

use App\Constants;
use App\Enums\KnowiiFollowReason;
use App\Enums\KnowiiNotificationKind;
use App\Enums\KnowiiPageKind;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\NotificationFollow;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The content of a bundled row (#1474, #1475, ADR 0042, design 5.1, 6.6, 6.9): up to 3 names, how many others, and how
 * many items it holds. They come from the live content, not from the row, so a deleted item or a blocked author is never
 * named or counted. All the rows of a page load with a fixed number of queries.
 *
 * - `post_comment`: the first-level comments on its post since its subject.
 * - `comment_reply`: the replies to its parent comment since its subject.
 * - `thread_comment`: the comments and the replies in its post thread since its subject.
 * - `thread_reply`: the replies in its chat thread since its subject.
 * - `space_post`: the posts of its space since its subject.
 * - `space_chat`: the first-level messages of its chat space since its subject.
 * - `lesson_new`: the lessons of its course since its subject.
 * - `event_new`: one event, never bundled (#1513): its host, unless blocked, and a count of 1 while the event exists.
 * - `mention`: one author, one item.
 *
 * The rows of a thread also get the reason why the member follows it.
 */
final class NotificationBundles
{
    /**
     * Load what the resource shows and set `bundledActors`, `others_count`, `items_count` and `follow_reason` on each row.
     *
     * @param  Collection<int, Notification>  $rows  the rows of one member
     */
    public static function attach(User $user, Collection $rows): void
    {
        $rows->loadMissing(['community', 'space', 'actor', 'post', 'page', 'event', 'comment.post', 'comment.communityResource', 'chatMessage.parent']);

        $blocked = array_values(DB::table('user_blocks')->where('blocker_id', $user->id)->pluck('blocked_id')->map(static fn ($id): int => is_numeric($id) ? (int) $id : 0)->all());
        $bundles = self::bundles($user, $rows, $blocked);
        $followReasons = self::followReasons($user, $rows);

        $wanted = [];
        foreach ($rows as $row) {
            $ids = $bundles[$row->id]['authors'] ?? self::fallback($row, $blocked);
            $wanted = [...$wanted, ...array_slice($ids, 0, Constants::NOTIFICATIONS_MAX_ACTORS)];
        }
        /** @var Collection<int, User> $users */
        $users = $wanted === [] ? new Collection : User::query()->whereIn('id', array_unique($wanted))->get()->keyBy('id');

        foreach ($rows as $row) {
            $ids = $bundles[$row->id]['authors'] ?? self::fallback($row, $blocked);
            $named = array_slice($ids, 0, Constants::NOTIFICATIONS_MAX_ACTORS);
            $row->setRelation('bundledActors', new Collection(array_values(array_filter(
                array_map(static fn (int $id): ?User => $users->get($id), $named),
            ))));
            $row->setAttribute('others_count', max(0, count($ids) - count($named)));
            $row->setAttribute('items_count', $bundles[$row->id]['count'] ?? 0);
            $row->setAttribute('follow_reason', $followReasons[$row->id] ?? null);
        }
    }

    /**
     * The author ids (newest author first) and the number of live items of every bundle row, by row id. A row without live
     * items has no entry.
     *
     * @param  Collection<int, Notification>  $rows
     * @param  list<int>  $blocked
     * @return array<int, array{authors: list<int>, count: int}>
     */
    private static function bundles(User $user, Collection $rows, array $blocked): array
    {
        // Per family of items: the rows and where each one starts, as [row id, scope, first id]
        $postTop = [];
        $postAll = [];
        $replies = [];
        $spacePosts = [];
        $chatThreads = [];
        $chatSpaces = [];
        $lessons = [];
        foreach ($rows as $row) {
            $comment = $row->comment;
            $message = $row->chatMessage;
            if ($row->kind === KnowiiNotificationKind::PostComment && $comment?->post_id !== null) {
                $postTop[$row->id] = [$comment->post_id, $comment->id];
            } elseif ($row->kind === KnowiiNotificationKind::CommentReply && $comment?->parent_id !== null) {
                $replies[$row->id] = [$comment->parent_id, $comment->id];
            } elseif ($row->kind === KnowiiNotificationKind::ThreadComment && $comment?->post_id !== null) {
                $postAll[$row->id] = [$comment->post_id, $comment->id];
            } elseif ($row->kind === KnowiiNotificationKind::SpacePost && $row->post !== null) {
                $spacePosts[$row->id] = [$row->post->space_id, $row->post->id];
            } elseif ($row->kind === KnowiiNotificationKind::ThreadReply && $message?->parent_id !== null) {
                $chatThreads[$row->id] = [$message->parent_id, $message->id];
            } elseif ($row->kind === KnowiiNotificationKind::SpaceChat && $message?->space_id !== null) {
                $chatSpaces[$row->id] = [$message->space_id, $message->id];
            } elseif ($row->kind === KnowiiNotificationKind::LessonNew && $row->page !== null) {
                $lessons[$row->id] = [$row->page->space_id, $row->page->id];
            }
        }

        $bundles = [];

        // An event is one item: its host, unless the host is blocked or the member (#1513)
        foreach ($rows as $row) {
            $host = $row->event?->host_id;
            if ($row->kind === KnowiiNotificationKind::EventNew && $row->event !== null) {
                $bundles[$row->id] = ['authors' => $host === null || $host === $user->id || in_array($host, $blocked, true) ? [] : [$host], 'count' => 1];
            }
        }

        if ($postTop !== [] || $postAll !== [] || $replies !== []) {
            $comments = Comment::query()
                ->where('id', '>=', self::lowest(array_column([...$postTop, ...$postAll, ...$replies], 1)))
                ->whereNotNull('author_id')
                ->where('author_id', '!=', $user->id)
                ->when($blocked !== [], static fn (Builder $query) => $query->whereNotIn('author_id', $blocked))
                ->where(static function (Builder $query) use ($postTop, $postAll, $replies): void {
                    $query->whereRaw('1 = 0');
                    if ($postTop !== []) {
                        $query->orWhere(static fn (Builder $inner) => $inner->whereIn('post_id', array_unique(array_column($postTop, 0)))->whereNull('parent_id'));
                    }
                    if ($postAll !== []) {
                        $query->orWhereIn('post_id', array_unique(array_column($postAll, 0)));
                    }
                    if ($replies !== []) {
                        $query->orWhereIn('parent_id', array_unique(array_column($replies, 0)));
                    }
                })
                ->orderByDesc('id')
                ->get(['id', 'post_id', 'parent_id', 'author_id']);

            foreach ($postTop as $rowId => [$postId, $firstId]) {
                $bundles[$rowId] = self::summary($comments->filter(static fn (Comment $comment): bool => $comment->parent_id === null && $comment->post_id === $postId && $comment->id >= $firstId));
            }
            foreach ($postAll as $rowId => [$postId, $firstId]) {
                $bundles[$rowId] = self::summary($comments->filter(static fn (Comment $comment): bool => $comment->post_id === $postId && $comment->id >= $firstId));
            }
            foreach ($replies as $rowId => [$parentId, $firstId]) {
                $bundles[$rowId] = self::summary($comments->filter(static fn (Comment $comment): bool => $comment->parent_id === $parentId && $comment->id >= $firstId));
            }
        }

        if ($spacePosts !== []) {
            $posts = Post::query()
                ->whereIn('space_id', array_unique(array_column($spacePosts, 0)))
                ->where('id', '>=', self::lowest(array_column($spacePosts, 1)))
                ->whereNull('deleted_at')
                ->whereNotNull('author_id')
                ->where('author_id', '!=', $user->id)
                ->when($blocked !== [], static fn (Builder $query) => $query->whereNotIn('author_id', $blocked))
                ->orderByDesc('id')
                ->get(['id', 'space_id', 'author_id']);

            foreach ($spacePosts as $rowId => [$spaceId, $firstId]) {
                $bundles[$rowId] = self::summary($posts->filter(static fn (Post $post): bool => $post->space_id === $spaceId && $post->id >= $firstId));
            }
        }

        if ($chatThreads !== [] || $chatSpaces !== []) {
            $messages = ChatMessage::query()
                ->where('id', '>=', self::lowest(array_column([...$chatThreads, ...$chatSpaces], 1)))
                ->whereNull('deleted_at')
                ->whereNotNull('author_id')
                ->where('author_id', '!=', $user->id)
                ->when($blocked !== [], static fn (Builder $query) => $query->whereNotIn('author_id', $blocked))
                ->where(static function (Builder $query) use ($chatThreads, $chatSpaces): void {
                    $query->whereRaw('1 = 0');
                    if ($chatThreads !== []) {
                        $query->orWhereIn('parent_id', array_unique(array_column($chatThreads, 0)));
                    }
                    if ($chatSpaces !== []) {
                        $query->orWhere(static fn (Builder $inner) => $inner->whereIn('space_id', array_unique(array_column($chatSpaces, 0)))->whereNull('parent_id'));
                    }
                })
                ->orderByDesc('id')
                ->get(['id', 'space_id', 'parent_id', 'author_id']);

            foreach ($chatThreads as $rowId => [$rootId, $firstId]) {
                $bundles[$rowId] = self::summary($messages->filter(static fn (ChatMessage $message): bool => $message->parent_id === $rootId && $message->id >= $firstId));
            }
            foreach ($chatSpaces as $rowId => [$spaceId, $firstId]) {
                $bundles[$rowId] = self::summary($messages->filter(static fn (ChatMessage $message): bool => $message->parent_id === null && $message->space_id === $spaceId && $message->id >= $firstId));
            }
        }

        if ($lessons !== []) {
            $pages = Page::query()
                ->whereIn('space_id', array_unique(array_column($lessons, 0)))
                ->where('kind', KnowiiPageKind::Page->value)
                ->where('id', '>=', self::lowest(array_column($lessons, 1)))
                ->orderByDesc('id')
                ->get(['id', 'space_id', 'author_id']);

            foreach ($lessons as $rowId => [$spaceId, $firstId]) {
                // A lesson of a blocked author is not counted, a lesson without author (a deleted account) is
                $bundles[$rowId] = self::summary($pages->filter(static fn (Page $page): bool => $page->space_id === $spaceId && $page->id >= $firstId && $page->author_id !== $user->id && ! in_array($page->author_id, $blocked, true)));
            }
        }

        return array_filter($bundles, static fn (array $bundle): bool => $bundle['count'] > 0);
    }

    /**
     * @param  list<int>  $ids
     */
    private static function lowest(array $ids): int
    {
        return $ids === [] ? 0 : min($ids);
    }

    /**
     * @template TItem of Comment|Post|ChatMessage|Page
     *
     * @param  Collection<int, TItem>  $items  newest first
     * @return array{authors: list<int>, count: int}
     */
    private static function summary(Collection $items): array
    {
        $authors = $items->pluck('author_id')->filter()->unique()->map(static fn ($id): int => is_numeric($id) ? (int) $id : 0)->values()->all();

        return ['authors' => array_values($authors), 'count' => $items->count()];
    }

    /**
     * Why the member follows the thread of each thread row (wrote, replied, manual), by row id, with one query for all rows
     *
     * @param  Collection<int, Notification>  $rows
     * @return array<int, KnowiiFollowReason>
     */
    private static function followReasons(User $user, Collection $rows): array
    {
        $postRows = [];
        $chatRows = [];
        foreach ($rows as $row) {
            if ($row->kind === KnowiiNotificationKind::ThreadComment && $row->comment?->post_id !== null) {
                $postRows[$row->id] = $row->comment->post_id;
            } elseif ($row->kind === KnowiiNotificationKind::ThreadReply && $row->chatMessage?->parent_id !== null) {
                $chatRows[$row->id] = $row->chatMessage->parent_id;
            }
        }
        if ($postRows === [] && $chatRows === []) {
            return [];
        }

        $follows = NotificationFollow::query()
            ->where('user_id', $user->id)
            ->where(static function (Builder $query) use ($postRows, $chatRows): void {
                $query->whereRaw('1 = 0');
                if ($postRows !== []) {
                    $query->orWhereIn('post_id', array_unique($postRows));
                }
                if ($chatRows !== []) {
                    $query->orWhereIn('chat_message_id', array_unique($chatRows));
                }
            })
            ->get(['post_id', 'chat_message_id', 'reason']);

        $reasons = [];
        foreach ($postRows as $rowId => $postId) {
            $follow = $follows->first(static fn (NotificationFollow $follow): bool => $follow->post_id === $postId);
            if ($follow !== null) {
                $reasons[$rowId] = $follow->reason;
            }
        }
        foreach ($chatRows as $rowId => $rootId) {
            $follow = $follows->first(static fn (NotificationFollow $follow): bool => $follow->chat_message_id === $rootId);
            if ($follow !== null) {
                $reasons[$rowId] = $follow->reason;
            }
        }

        return $reasons;
    }

    /**
     * The author of a row without a bundle: its last actor, unless blocked or deleted
     *
     * @param  list<int>  $blocked
     * @return list<int>
     */
    private static function fallback(Notification $row, array $blocked): array
    {
        if ($row->actor_id === null || in_array($row->actor_id, $blocked, true)) {
            return [];
        }

        return [$row->actor_id];
    }
}
