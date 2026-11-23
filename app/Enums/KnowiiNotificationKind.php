<?php

namespace App\Enums;

/**
 * The kinds of notifications (#1474, ADR 0042). Each kind has one level, and the level gives the channel: a later kind
 * adds a case here, never a rule of its own. The level 1 kinds ("Needs you") show in the bell and in the list.
 */
enum KnowiiNotificationKind: string
{
    // A first-level comment on my post
    case PostComment = 'post_comment';
    // A reply to my comment
    case CommentReply = 'comment_reply';
    // A mention in a post, a comment or a chat message
    case Mention = 'mention';
    // A new comment in a post thread that the member follows (#1475)
    case ThreadComment = 'thread_comment';
    // A new reply in a chat thread that the member follows (#1475)
    case ThreadReply = 'thread_reply';
    // A new post in a discussion space where the member chose "Everything" (or the space sends to everyone) (#1475)
    case SpacePost = 'space_post';
    // New messages in a chat space where the member chose "Everything" (#1475)
    case SpaceChat = 'space_chat';
    // A new lesson in a course that the member started (#1475)
    case LessonNew = 'lesson_new';
    // A new event in an events space where the member is at "Everything" (#1513, ADR 0053)
    case EventNew = 'event_new';
    // A first-level comment on an event that the member hosts (#1516, ADR 0053)
    case EventComment = 'event_comment';

    /**
     * 0 Account, 1 Needs you, 2 Following, 3 Around you (design 2.1).
     */
    final public function level(): int
    {
        return match ($this) {
            self::PostComment, self::CommentReply, self::Mention, self::EventComment => 1,
            self::ThreadComment, self::ThreadReply, self::SpacePost, self::SpaceChat, self::LessonNew, self::EventNew => 2,
        };
    }

    /**
     * The code of the reason, which the client turns into a sentence (design 8.1)
     */
    final public function reason(): KnowiiNotificationReason
    {
        return match ($this) {
            self::PostComment => KnowiiNotificationReason::WrotePost,
            self::CommentReply => KnowiiNotificationReason::WroteComment,
            self::Mention => KnowiiNotificationReason::Mentioned,
            self::ThreadComment, self::ThreadReply => KnowiiNotificationReason::FollowsThread,
            self::SpacePost, self::SpaceChat, self::EventNew => KnowiiNotificationReason::SpaceEverything,
            self::LessonNew => KnowiiNotificationReason::StartedCourse,
            self::EventComment => KnowiiNotificationReason::HostsEvent,
        };
    }

    /**
     * The kinds of a level, as stored values
     *
     * @return list<string>
     */
    final public static function valuesOfLevel(int $level): array
    {
        return array_values(array_map(
            static fn (self $kind): string => $kind->value,
            array_filter(self::cases(), static fn (self $kind): bool => $kind->level() === $level),
        ));
    }

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
