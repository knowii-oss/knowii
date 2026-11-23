<?php

namespace App\Actions\Notifications;

use App\Contracts\Notifications\PrunesNotifications;
use App\Enums\KnowiiNotificationKind;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Event;
use App\Models\Notification;
use App\Models\NotificationFollow;
use App\Models\NotificationSpaceLevel;
use App\Models\Page;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use App\Support\NotificationAccess;
use App\Support\NotificationEvents;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes the rows that must go (#1474, ADR 0042, design 6.8, 6.9). The database cascades cover a deleted post,
 * comment, space, community and account. This action covers the rest: access that changed, a member who left, a block,
 * and a chat message, post or comment that is only a marker (a soft delete).
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

        // The follows and the levels of the members who lost the access go too (#1475): only the rows count here
        NotificationAccess::pruneChoices($community, $space);

        return NotificationAccess::prune($rows);
    }

    final public function forMember(User $member, Community $community): int
    {
        // Their choices in this community go with them (#1475, ADR 0042)
        NotificationFollow::query()->where('user_id', $member->id)->where('community_id', $community->id)->delete();
        NotificationSpaceLevel::query()->where('user_id', $member->id)->whereIn('space_id', $community->spaces()->select('id'))->delete();

        return $this->delete(Notification::query()->where('community_id', $community->id)->where('user_id', $member->id));
    }

    final public function forBlock(User $blocker, User $blocked): int
    {
        // "Caused": the last author of the row, or the author of its item (the first comment that the member did not read)
        return $this->delete(Notification::query()->where('user_id', $blocker->id)->where(function (Builder $query) use ($blocked): void {
            $query->where('actor_id', $blocked->id)
                ->orWhereIn('post_id', Post::query()->where('author_id', $blocked->id)->select('id'))
                ->orWhereIn('comment_id', Comment::query()->where('author_id', $blocked->id)->select('id'))
                ->orWhereIn('chat_message_id', ChatMessage::query()->where('author_id', $blocked->id)->select('id'))
                ->orWhereIn('page_id', Page::query()->where('author_id', $blocked->id)->select('id'))
                // The events that the blocked member hosts (#1513)
                ->orWhereIn('event_id', Event::query()->where('host_id', $blocked->id)->select('id'));
        }));
    }

    final public function forChatMessage(int $chatMessageId): int
    {
        // A marker loses its follows (ADR 0043)
        NotificationFollow::query()->where('chat_message_id', $chatMessageId)->delete();

        return $this->delete(Notification::query()->where('chat_message_id', $chatMessageId));
    }

    final public function forPosts(array $postIds): int
    {
        if ($postIds === []) {
            return 0;
        }
        // A marker loses its follows (ADR 0043)
        NotificationFollow::query()->whereIn('post_id', $postIds)->delete();

        return $this->delete(Notification::query()->whereIn('post_id', $postIds));
    }

    final public function forComments(array $commentIds): int
    {
        return $commentIds === [] ? 0 : $this->delete(Notification::query()->whereIn('comment_id', $commentIds));
    }

    final public function forChatMessages(array $chatMessageIds): int
    {
        if ($chatMessageIds === []) {
            return 0;
        }
        // A marker loses its follows (ADR 0043)
        NotificationFollow::query()->whereIn('chat_message_id', $chatMessageIds)->delete();

        return $this->delete(Notification::query()->whereIn('chat_message_id', $chatMessageIds));
    }

    final public function forDraftEvent(Event $event): int
    {
        return $this->delete(Notification::query()->where('event_id', $event->id)->where('kind', KnowiiNotificationKind::EventNew->value));
    }

    final public function forFormerHost(Event $event, int $formerHostId): int
    {
        return $this->delete(Notification::query()
            ->where('user_id', $formerHostId)
            ->where('kind', KnowiiNotificationKind::EventComment->value)
            ->whereIn('comment_id', Comment::query()->select('id')->where('event_id', $event->id)));
    }

    final public function forMutedSpace(User $member, Space $space): int
    {
        return $this->delete(Notification::query()->where('user_id', $member->id)->where('space_id', $space->id)->whereNull('read_at'));
    }

    final public function forMutedThread(User $member, Post|ChatMessage $thread): int
    {
        // The rows of a space are not about one thread: the ones that a new post or message in the space bundles stay
        $rows = Notification::query()
            ->where('user_id', $member->id)
            ->whereNull('read_at')
            ->whereNotIn('kind', [KnowiiNotificationKind::SpacePost->value, KnowiiNotificationKind::SpaceChat->value, KnowiiNotificationKind::LessonNew->value, KnowiiNotificationKind::EventNew->value]);

        return $this->delete($thread instanceof Post
            ? $rows->where(function (Builder $query) use ($thread): void {
                $query->where('post_id', $thread->id)->orWhereIn('comment_id', Comment::query()->where('post_id', $thread->id)->select('id'));
            })
            : $rows->where(function (Builder $query) use ($thread): void {
                $query->where('chat_message_id', $thread->id)->orWhereIn('chat_message_id', ChatMessage::query()->where('parent_id', $thread->id)->select('id'));
            }));
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
