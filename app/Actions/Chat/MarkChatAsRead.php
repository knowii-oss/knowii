<?php

namespace App\Actions\Chat;

use App\Contracts\Chat\MarksChatAsRead;
use App\Models\ChatRead;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Remember the last first-level message that a member read in a chat space (#1185): the "new messages" line and the
 * unread count of the space navigation start after it. The marker never goes back.
 */
class MarkChatAsRead implements MarksChatAsRead
{
    final public function markAsRead(User $user, Space $space, array $input): void
    {
        Gate::forUser($user)->authorize('view', $space);
        if (! $user->belongsToCommunity($space->community)) {
            return;
        }

        /** @var array{message_cuid: string} $validated */
        $validated = Validator::make($input, ['message_cuid' => ['required', 'string']])->validate();

        $id = $space->chatMessages()->whereNull('parent_id')->where('cuid', $validated['message_cuid'])->value('id');
        if (! is_numeric($id)) {
            return;
        }

        $read = ChatRead::query()->firstOrNew(['user_id' => $user->id, 'space_id' => $space->id]);
        $read->last_read_message_id = max($read->last_read_message_id ?? 0, (int) $id);
        $read->save();
    }
}
