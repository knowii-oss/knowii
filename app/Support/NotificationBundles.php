<?php

namespace App\Support;

use App\Constants;
use App\Enums\KnowiiNotificationKind;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * The authors of a bundled row (#1474, ADR 0042, design 5.1, 6.6, 6.9): up to 3 names, and how many others. They come
 * from the live comments, not from the row, so a deleted comment or a blocked author is never named or counted. All
 * the rows of a page load with a fixed number of queries.
 *
 * A "post_comment" row bundles the first-level comments on its post since its subject; a "comment_reply" row bundles
 * the replies to its parent comment since its subject. A "mention" row has one author.
 */
final class NotificationBundles
{
    /**
     * Load what the resource shows and set `bundledActors` and `others_count` on each row.
     *
     * @param  Collection<int, Notification>  $rows  the rows of one member
     */
    public static function attach(User $user, Collection $rows): void
    {
        $rows->loadMissing(['community', 'space', 'actor', 'post', 'comment.post', 'comment.communityResource', 'chatMessage.parent']);

        $blocked = array_values(DB::table('user_blocks')->where('blocker_id', $user->id)->pluck('blocked_id')->map(static fn ($id): int => is_numeric($id) ? (int) $id : 0)->all());
        $authors = self::authorsOfBundles($user, $rows, $blocked);

        $wanted = [];
        foreach ($rows as $row) {
            $ids = $authors[$row->id] ?? self::fallback($row, $blocked);
            $wanted = [...$wanted, ...array_slice($ids, 0, Constants::NOTIFICATIONS_MAX_ACTORS)];
        }
        /** @var Collection<int, User> $users */
        $users = $wanted === [] ? new Collection : User::query()->whereIn('id', array_unique($wanted))->get()->keyBy('id');

        foreach ($rows as $row) {
            $ids = $authors[$row->id] ?? self::fallback($row, $blocked);
            $named = array_slice($ids, 0, Constants::NOTIFICATIONS_MAX_ACTORS);
            $row->setRelation('bundledActors', new Collection(array_values(array_filter(
                array_map(static fn (int $id): ?User => $users->get($id), $named),
            ))));
            $row->setAttribute('others_count', max(0, count($ids) - count($named)));
        }
    }

    /**
     * The author ids of every bundle row, newest author first, by row id. A row without live comments has no entry.
     *
     * @param  Collection<int, Notification>  $rows
     * @param  list<int>  $blocked
     * @return array<int, list<int>>
     */
    private static function authorsOfBundles(User $user, Collection $rows, array $blocked): array
    {
        $firstIds = [];
        $postRows = [];
        $replyRows = [];
        foreach ($rows as $row) {
            if ($row->comment === null) {
                continue;
            }
            if ($row->kind === KnowiiNotificationKind::PostComment && $row->comment->post_id !== null) {
                $postRows[$row->id] = [$row->comment->post_id, $row->comment->id];
                $firstIds[] = $row->comment->id;
            } elseif ($row->kind === KnowiiNotificationKind::CommentReply && $row->comment->parent_id !== null) {
                $replyRows[$row->id] = [$row->comment->parent_id, $row->comment->id];
                $firstIds[] = $row->comment->id;
            }
        }
        if ($firstIds === []) {
            return [];
        }

        $postIds = array_unique(array_column($postRows, 0));
        $parentIds = array_unique(array_column($replyRows, 0));
        $comments = Comment::query()
            ->where('id', '>=', min($firstIds))
            ->whereNotNull('author_id')
            ->where('author_id', '!=', $user->id)
            ->when($blocked !== [], static fn (Builder $query) => $query->whereNotIn('author_id', $blocked))
            ->where(static function (Builder $query) use ($postIds, $parentIds): void {
                $query->where(static fn (Builder $inner) => $inner->whereIn('post_id', $postIds)->whereNull('parent_id'))
                    ->orWhereIn('parent_id', $parentIds);
            })
            ->orderByDesc('id')
            ->get(['id', 'post_id', 'parent_id', 'author_id']);

        $authors = [];
        foreach ($postRows as $rowId => [$postId, $firstId]) {
            $authors[$rowId] = self::distinctAuthors($comments->filter(static fn (Comment $comment): bool => $comment->parent_id === null && $comment->post_id === $postId && $comment->id >= $firstId));
        }
        foreach ($replyRows as $rowId => [$parentId, $firstId]) {
            $authors[$rowId] = self::distinctAuthors($comments->filter(static fn (Comment $comment): bool => $comment->parent_id === $parentId && $comment->id >= $firstId));
        }

        return array_filter($authors, static fn (array $ids): bool => $ids !== []);
    }

    /**
     * @param  SupportCollection<int, Comment>  $comments  newest first
     * @return list<int>
     */
    private static function distinctAuthors(SupportCollection $comments): array
    {
        return array_values($comments->pluck('author_id')->unique()->map(static fn ($id): int => is_numeric($id) ? (int) $id : 0)->all());
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
