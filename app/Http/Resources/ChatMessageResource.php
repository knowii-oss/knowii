<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use Illuminate\Http\Request;

/**
 * A chat message (#1185). WARNING: aligned with chatMessageSchema (chat-message.schema.ts)
 *
 * @mixin ChatMessage
 */
class ChatMessageResource extends AbstractKnowiiJsonResource
{
    /**
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        $deleted = $this->isDeleted();

        return [
            'cuid' => $this->cuid,
            // A deleted message is a marker: no text, no author
            'body' => $deleted ? null : $this->body,
            'body_html' => $deleted ? null : $this->body_html,
            'author' => $deleted ? null : $this->author?->toResource(AuthorResource::class),
            'parent_cuid' => $this->parent_id === null ? null : ($this->relationLoaded('parent') ? $this->parent?->cuid : $this->parent()->value('cuid')),
            'reply_count' => $this->reply_count,
            'last_reply_at' => $this->last_reply_at,
            'edited_at' => $this->edited_at,
            'deleted_at' => $this->deleted_at,
            'reactions' => $this->when($this->relationLoaded('reactions'), fn () => $deleted ? [] : $this->reactionSummary($request->user())),
            'created_at' => $this->created_at,
        ];
    }
}
