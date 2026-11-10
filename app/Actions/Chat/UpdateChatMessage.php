<?php

namespace App\Actions\Chat;

use App\Contracts\Chat\UpdatesChatMessages;
use App\Models\ChatMessage;
use App\Models\User;
use App\Support\ChatMessageEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * The author changes the text of their message (#1185). The message shows "edited".
 */
class UpdateChatMessage implements UpdatesChatMessages
{
    final public function update(User $user, ChatMessage $message, array $input): ChatMessage
    {
        Gate::forUser($user)->authorize('update', $message);

        /** @var array{body: string} $validated */
        $validated = Validator::make($input, CreateChatMessage::rules())->validate();

        DB::transaction(function () use ($message, $user, $validated) {
            $message->update([
                'body' => $validated['body'],
                'body_html' => $message->renderBodyAndSyncMentions($validated['body'], $user),
                'edited_at' => now(),
            ]);
            $message->syncUploadUsages($validated['body']);
        });

        Log::info('Chat message updated', ['message' => $message->cuid]);
        ChatMessageEvents::dispatch(ChatMessageEvents::UPDATED, $message, $user->cuid);

        return $message;
    }
}
