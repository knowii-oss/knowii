<?php

namespace App\Support;

use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Page;
use App\Models\Post;
use App\Models\Space;

/**
 * Where an item of a notification lives (#1474, #1475, ADR 0042): its space, and its thread. The one place that knows it.
 */
final class NotificationItem
{
    /**
     * The space of the item, with what the access check reads (the access groups of the space and of its space group)
     */
    /**
     * The member who caused the item: its author, or the host of an event (#1513). Null when the account was deleted
     */
    public static function actorOf(Post|Comment|ChatMessage|Page|Event $item): ?int
    {
        return $item instanceof Event ? $item->host_id : $item->author_id;
    }

    public static function spaceOf(Post|Comment|ChatMessage|Page|Event $item): ?Space
    {
        $spaceId = match (true) {
            $item instanceof Comment => $item->post->space_id ?? $item->communityResource->space_id ?? $item->event?->space_id,
            default => $item->space_id,
        };

        return $spaceId === null ? null : Space::query()->with(['accessGroups', 'spaceGroup.accessGroups'])->find($spaceId);
    }

    /**
     * The thread of the item: a post with its comments, or a first-level chat message with its replies. Null for the
     * items that are in no thread (a comment on a resource or on an event, a page, an event: only its space can be
     * muted).
     *
     * @return array{column: 'post_id'|'chat_message_id', id: int}|null
     */
    public static function threadOf(Post|Comment|ChatMessage|Page|Event $item): ?array
    {
        return match (true) {
            $item instanceof Post => ['column' => 'post_id', 'id' => $item->id],
            $item instanceof Comment => $item->post_id === null ? null : ['column' => 'post_id', 'id' => $item->post_id],
            $item instanceof ChatMessage => ['column' => 'chat_message_id', 'id' => $item->parent_id ?? $item->id],
            default => null,
        };
    }
}
