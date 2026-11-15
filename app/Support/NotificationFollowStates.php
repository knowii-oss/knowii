<?php

namespace App\Support;

use App\Models\ChatMessage;
use App\Models\NotificationFollow;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The follow state of the current member for the posts and the chat threads of a page (#1475, ADR 0042): one query for the
 * whole page, never one per item. The row (or null) goes on each item as the relation `follow`, which the resources read.
 */
final class NotificationFollowStates
{
    /**
     * @param  iterable<Post|ChatMessage>  $threads  the posts and the first-level messages of the page
     */
    public static function attach(?User $user, iterable $threads): void
    {
        if ($user === null) {
            return;
        }

        $items = Collection::make(is_array($threads) ? $threads : iterator_to_array($threads, false))
            ->filter(static fn (Post|ChatMessage $item): bool => $item instanceof Post || $item->parent_id === null)
            ->values();
        if ($items->isEmpty()) {
            return;
        }

        $postIds = $items->filter(static fn (Post|ChatMessage $item): bool => $item instanceof Post)->map(static fn (Post|ChatMessage $item): int => $item->id)->values()->all();
        $messageIds = $items->filter(static fn (Post|ChatMessage $item): bool => $item instanceof ChatMessage)->map(static fn (Post|ChatMessage $item): int => $item->id)->values()->all();

        $follows = NotificationFollow::query()
            ->where('user_id', $user->id)
            ->where(static function ($query) use ($postIds, $messageIds): void {
                $query->whereRaw('1 = 0');
                if ($postIds !== []) {
                    $query->orWhereIn('post_id', $postIds);
                }
                if ($messageIds !== []) {
                    $query->orWhereIn('chat_message_id', $messageIds);
                }
            })
            ->get();

        foreach ($items as $item) {
            $follow = $follows->first(static fn (NotificationFollow $follow): bool => $item instanceof Post ? $follow->post_id === $item->id : $follow->chat_message_id === $item->id);
            $item->setRelation('follow', $follow);
        }
    }
}
