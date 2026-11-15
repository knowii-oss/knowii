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

    /**
     * 0 Account, 1 Needs you, 2 Following, 3 Around you (design 2.1).
     */
    final public function level(): int
    {
        return match ($this) {
            self::PostComment, self::CommentReply, self::Mention => 1,
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
