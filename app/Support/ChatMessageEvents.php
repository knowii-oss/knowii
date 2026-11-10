<?php

namespace App\Support;

use App\Events\Conversations\ConversationChanged;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\ChatMessage;

/**
 * The real-time event of a chat message, on the channel of its place: the chat space (SpaceContentChanged) or the
 * conversation (ConversationChanged, #1186).
 */
final class ChatMessageEvents
{
    public const string CREATED = 'created';

    public const string UPDATED = 'updated';

    public const string DELETED = 'deleted';

    public const string REACTIONS = 'reactions';

    public static function dispatch(string $change, ChatMessage $message, ?string $authorCuid = null): void
    {
        if ($message->isDirectMessage()) {
            event(ConversationChanged::forMessage(match ($change) {
                self::CREATED => ConversationChanged::MESSAGE_CREATED,
                self::UPDATED => ConversationChanged::MESSAGE_UPDATED,
                self::DELETED => ConversationChanged::MESSAGE_DELETED,
                default => ConversationChanged::REACTIONS_CHANGED,
            }, $message, $authorCuid));

            return;
        }

        event($change === self::REACTIONS
            ? SpaceContentChanged::forReactions($message)
            : SpaceContentChanged::forMessage(match ($change) {
                self::CREATED => SpaceContentChanged::MESSAGE_CREATED,
                self::UPDATED => SpaceContentChanged::MESSAGE_UPDATED,
                default => SpaceContentChanged::MESSAGE_DELETED,
            }, $message, $authorCuid));
    }
}
