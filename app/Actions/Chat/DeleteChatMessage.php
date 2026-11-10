<?php

namespace App\Actions\Chat;

use App\Contracts\Chat\DeletesChatMessages;
use App\Models\ChatMessage;
use App\Models\User;
use App\Support\ChatMessageEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * The author, or a member who moderates the space, deletes a message (#1185). The message stays as a marker
 * ("This message was deleted"), so its thread keeps its place; its text, mentions, reactions and image usages go.
 */
class DeleteChatMessage implements DeletesChatMessages
{
    final public function delete(User $user, ChatMessage $message): void
    {
        Gate::forUser($user)->authorize('delete', $message);

        DB::transaction(static function () use ($message) {
            $message->update(['body' => '', 'body_html' => '', 'deleted_at' => now()]);
            $message->mentions()->delete();
            $message->reactions()->delete();
            $message->uploadUsages()->delete();

            $parent = $message->parent;
            if ($parent !== null) {
                $last = $parent->replies()->whereNull('deleted_at')->max('created_at');
                $parent->update([
                    'reply_count' => $parent->replies()->whereNull('deleted_at')->count(),
                    'last_reply_at' => $last,
                ]);
            }
        });

        Log::info('Chat message deleted', ['message' => $message->cuid]);
        ChatMessageEvents::dispatch(ChatMessageEvents::DELETED, $message, $user->cuid);
    }
}
