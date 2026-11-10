<?php

namespace App\Actions\Conversations;

use App\Contracts\Conversations\MarksConversationsAsRead;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Remember the last message that a participant read (#1186): the unread counts start after it. The marker never goes
 * back.
 */
class MarkConversationAsRead implements MarksConversationsAsRead
{
    final public function markAsRead(User $user, Conversation $conversation, array $input): void
    {
        Gate::forUser($user)->authorize('view', $conversation);

        /** @var array{message_cuid: string} $validated */
        $validated = Validator::make($input, ['message_cuid' => ['required', 'string']])->validate();

        $id = $conversation->messages()->where('cuid', $validated['message_cuid'])->value('id');
        if (! is_numeric($id)) {
            return;
        }

        $current = $conversation->participants()->whereKey($user->id)->value('conversation_participants.last_read_message_id');
        if ((int) $id > (is_numeric($current) ? (int) $current : 0)) {
            $conversation->participants()->updateExistingPivot($user->id, ['last_read_message_id' => (int) $id]);
        }
    }
}
