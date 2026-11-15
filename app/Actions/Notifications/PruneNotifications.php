<?php

namespace App\Actions\Notifications;

use App\Contracts\Notifications\PrunesNotifications;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use App\Support\NotificationAccess;
use App\Support\NotificationEvents;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes the rows that must go (#1474, ADR 0042, design 6.8, 6.9). The database cascades cover a deleted post,
 * comment, space, community and account. This action covers the rest: access that changed, a member who left, a block,
 * and a chat message that is only soft-deleted.
 */
final class PruneNotifications implements PrunesNotifications
{
    final public function forAccess(Community $community, ?Space $space = null): int
    {
        $rows = Notification::query()->where('community_id', $community->id);

        if ($space !== null) {
            $rows->where('space_id', $space->id);
        } else {
            $rows->whereIn('space_id', $community->spaces()->where('visibility', KnowiiSpaceVisibility::Groups->value)->select('id'));
        }

        return NotificationAccess::prune($rows);
    }

    final public function forMember(User $member, Community $community): int
    {
        return $this->delete(Notification::query()->where('community_id', $community->id)->where('user_id', $member->id));
    }

    final public function forBlock(User $blocker, User $blocked): int
    {
        // "Caused": the last author of the row, or the author of its item (the first comment that the member did not read)
        return $this->delete(Notification::query()->where('user_id', $blocker->id)->where(function (Builder $query) use ($blocked): void {
            $query->where('actor_id', $blocked->id)
                ->orWhereIn('post_id', Post::query()->where('author_id', $blocked->id)->select('id'))
                ->orWhereIn('comment_id', Comment::query()->where('author_id', $blocked->id)->select('id'))
                ->orWhereIn('chat_message_id', ChatMessage::query()->where('author_id', $blocked->id)->select('id'));
        }));
    }

    final public function forChatMessage(int $chatMessageId): int
    {
        return $this->delete(Notification::query()->where('chat_message_id', $chatMessageId));
    }

    /**
     * @param  Builder<Notification>  $rows
     */
    private function delete(Builder $rows): int
    {
        $found = (clone $rows)->get(['notifications.id', 'notifications.cuid', 'notifications.user_id', 'notifications.community_id']);
        if ($found->isEmpty()) {
            return 0;
        }

        Notification::query()->whereIn('id', $found->modelKeys())->delete();
        NotificationEvents::changed($found);

        return $found->count();
    }
}
