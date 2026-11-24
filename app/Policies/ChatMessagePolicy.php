<?php

namespace App\Policies;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Gate;

/**
 * Chat messages (#1185): members who can write in the space chat and react; the author edits and deletes their
 * messages, while still a member of the community who can read the space (#1324); the members who moderate the space
 * delete any message. Direct messages (#1186) follow their conversation: the participants who can write react, the
 * author edits while they may still write in the conversation (ConversationPolicy::send) and deletes while they read
 * it, nobody moderates.
 */
class ChatMessagePolicy
{
    use HandlesAuthorization;

    final public function react(User $user, ChatMessage $message): bool
    {
        if ($message->conversation !== null) {
            return ! $message->isDeleted() && Gate::forUser($user)->check('send', $message->conversation);
        }

        return ! $message->isDeleted()
            && $user->belongsToCommunity($message->community)
            && Gate::forUser($user)->check('view', $message->space);
    }

    final public function update(User $user, ChatMessage $message): bool
    {
        if ($message->isDeleted() || $message->author_id !== $user->id) {
            return false;
        }

        // A direct message: a block, or a community that turned direct messages off, stops the edit like a new message
        if ($message->conversation !== null) {
            return Gate::forUser($user)->check('send', $message->conversation);
        }

        return $user->belongsToCommunity($message->community) && Gate::forUser($user)->check('view', $message->space);
    }

    final public function delete(User $user, ChatMessage $message): bool
    {
        if ($message->isDeleted() || ! Gate::forUser($user)->check('view', $message->conversation ?? $message->space)) {
            return false;
        }

        // The author of a direct message deletes it while they read the conversation (ConversationPolicy::view)
        if ($message->conversation !== null) {
            return $message->author_id === $user->id;
        }

        return ($message->author_id === $user->id && $user->belongsToCommunity($message->community))
            || Gate::forUser($user)->check('moderate', $message->space);
    }
}
