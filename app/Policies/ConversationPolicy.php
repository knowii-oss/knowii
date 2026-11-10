<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Direct messages (#1186, ADR 0028): only the participants who are still members of the community read a
 * conversation. They write in it while the community allows direct messages and, for a one-to-one conversation, while
 * neither of the two blocked the other.
 */
class ConversationPolicy
{
    use HandlesAuthorization;

    final public function view(User $user, Conversation $conversation): bool
    {
        return $user->belongsToCommunity($conversation->community) && $conversation->hasParticipant($user);
    }

    final public function send(User $user, Conversation $conversation): bool
    {
        if (! $this->view($user, $conversation) || ! $conversation->community->allowsDirectMessages()) {
            return false;
        }

        if (! $conversation->isDirect()) {
            return true;
        }

        $other = $conversation->participants()->whereKeyNot($user->id)->first();

        return $other !== null && ! $user->hasBlockWith($other);
    }
}
