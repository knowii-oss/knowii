<?php

namespace App\Events\Conversations;

use App\Constants;
use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A message of a conversation changed (#1186, ADR 0028). Identifiers only, like SpaceContentChanged: the page loads the
 * message through the API, which checks the access. On the private channel of the conversation (participants only),
 * and on the channel of each participant, so their lists and unread counts follow.
 */
class ConversationChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public const string MESSAGE_CREATED = 'conversation.message.created';

    public const string MESSAGE_UPDATED = 'conversation.message.updated';

    public const string MESSAGE_DELETED = 'conversation.message.deleted';

    public const string REACTIONS_CHANGED = 'conversation.reactions.changed';

    /**
     * @param  list<string>  $participantCuids
     */
    public function __construct(
        public readonly string $name,
        public readonly string $conversationCuid,
        public readonly string $messageCuid,
        public readonly ?string $authorCuid,
        public readonly array $participantCuids,
    ) {}

    public static function forMessage(string $name, ChatMessage $message, ?string $authorCuid = null): self
    {
        /** @var Conversation $conversation */
        $conversation = $message->conversation;

        /** @var list<string> $participantCuids */
        $participantCuids = $conversation->participants()->pluck('cuid')->map(fn (mixed $cuid) => is_string($cuid) ? $cuid : '')->values()->all();

        return new self($name, $conversation->cuid, $message->cuid, $authorCuid, $participantCuids);
    }

    /**
     * @return array<string, string|null>
     */
    public function broadcastWith(): array
    {
        return ['conversation_cuid' => $this->conversationCuid, 'message_cuid' => $this->messageCuid, 'author_cuid' => $this->authorCuid];
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel(str_replace(Constants::WS_CHANNEL_CONVERSATION_PARAM_CONVERSATION_CUID, $this->conversationCuid, Constants::WS_CHANNEL_CONVERSATION))];

        foreach ($this->participantCuids as $cuid) {
            $channels[] = new PrivateChannel(str_replace(Constants::WS_CHANNEL_USER_PARAM_USER_CUID, $cuid, Constants::WS_CHANNEL_USER));
        }

        return $channels;
    }
}
