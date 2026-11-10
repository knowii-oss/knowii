<?php

namespace App\Actions\Conversations;

use App\Actions\Chat\CreateChatMessage;
use App\Contracts\Conversations\SendsDirectMessages;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Support\ChatMessageEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Write a message in a conversation (#1186, ADR 0028): Markdown with mentions of the participants and images, like a
 * chat message. The author has read their own message.
 */
class SendDirectMessage implements SendsDirectMessages
{
    final public function send(User $user, Conversation $conversation, array $input): ChatMessage
    {
        Gate::forUser($user)->authorize('send', $conversation);

        /** @var array{body: string} $validated */
        $validated = Validator::make($input, CreateChatMessage::rules())->validate();

        $message = DB::transaction(static fn () => self::write($user, $conversation, $validated['body']));

        Log::info('Direct message sent', ['conversation' => $conversation->cuid, 'message' => $message->cuid]);
        ChatMessageEvents::dispatch(ChatMessageEvents::CREATED, $message, $user->cuid);

        return $message;
    }

    /**
     * Inside a transaction: the message, its mentions and images, the time of the conversation, the read marker of the
     * author
     */
    public static function write(User $user, Conversation $conversation, string $body): ChatMessage
    {
        $message = ChatMessage::create([
            'community_id' => $conversation->community_id,
            'conversation_id' => $conversation->id,
            'author_id' => $user->id,
            'body' => $body,
            'body_html' => '',
            // Explicit: the model does not read the database default after the insert
            'reply_count' => 0,
        ]);
        $message->setRelation('conversation', $conversation);
        $message->update(['body_html' => $message->renderBodyAndSyncMentions($body, $user)]);
        $message->syncUploadUsages($body);

        $conversation->update(['last_message_at' => $message->created_at]);
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_message_id' => $message->id]);

        return $message;
    }
}
