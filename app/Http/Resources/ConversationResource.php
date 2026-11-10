<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A conversation of direct messages (#1186): its participants, the newest message as a short text, and the messages
 * the current member did not read. WARNING: aligned with conversationSchema (conversation.schema.ts)
 *
 * @mixin Conversation
 */
class ConversationResource extends AbstractKnowiiJsonResource
{
    private const int EXCERPT_LENGTH = 120;

    /**
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var Conversation $conversation */
        $conversation = $this->resource;
        $unread = $conversation->getAttributes()['unread_count'] ?? null;

        return [
            'cuid' => $this->cuid,
            'is_direct' => $this->isDirect(),
            'participants' => $this->whenLoaded('participants', fn () => $this->participants->sortBy('name')->values()->toResourceCollection(AuthorResource::class)),
            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage === null ? null : self::excerpt($this->latestMessage)),
            'last_message_at' => $this->last_message_at,
            'unread_count' => $this->when(is_numeric($unread), fn () => is_numeric($unread) ? (int) $unread : 0),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array{cuid: string, text: string, author_name: ?string, deleted: bool}
     */
    private static function excerpt(ChatMessage $message): array
    {
        // Plain text: the Markdown signs go, the lines join
        $text = trim((string) preg_replace(['/[*_`>#~\[\]]+/', '/\(https?:\/\/[^)]*\)/', '/\s+/'], ['', '', ' '], $message->body));

        return [
            'cuid' => $message->cuid,
            'text' => $message->isDeleted() ? '' : Str::limit($text, self::EXCERPT_LENGTH, '…'),
            'author_name' => $message->isDeleted() ? null : $message->author?->name,
            'deleted' => $message->isDeleted(),
        ];
    }
}
