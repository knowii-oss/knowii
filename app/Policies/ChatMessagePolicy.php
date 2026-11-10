<?php

namespace App\Policies;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Gate;

/**
 * Chat messages (#1185): members who can write in the space chat and react; the author edits and deletes their
 * messages; the members who moderate the space delete any message. Direct messages (#1186) follow their conversation:
 * the participants who can write react, the author edits and deletes, nobody moderates.
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
        return ! $message->isDeleted()
            && $message->author_id === $user->id
            && Gate::forUser($user)->check('view', $message->conversation ?? $message->space);
    }

    final public function delete(User $user, ChatMessage $message): bool
    {
        if ($message->isDeleted() || ! Gate::forUser($user)->check('view', $message->conversation ?? $message->space)) {
            return false;
        }

        if ($message->conversation !== null) {
            return $message->author_id === $user->id;
        }

        return $message->author_id === $user->id || Gate::forUser($user)->check('moderate', $message->space);
    }
}
