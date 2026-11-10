<?php

namespace App\Broadcasting;

use App\Models\Conversation;
use App\Models\User;
use App\Support\Presence;

/**
 * Presence channel of a conversation (#1186, ADR 0027 and 0028): typing indicators between the participants who show
 * their online status.
 */
class ConversationPresenceChannel
{
    /**
     * @return array{cuid: string, name: string, username: string, avatar_url: ?string}|false
     */
    public function join(User $user, string $conversationCuid): array|false
    {
        $conversation = Conversation::whereCuid($conversationCuid)->first();

        if ($conversation === null || ! $user->showsOnlineStatus() || ! $user->can('view', $conversation)) {
            return false;
        }

        return Presence::info($user);
    }
}
