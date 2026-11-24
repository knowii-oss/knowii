<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use App\Support\MentionLinks;
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
            // A mention is a link for a member of the community only (MentionLinks): a signed-in person who is not a member
            // reads a public chat space
            'body_html' => $deleted ? null : MentionLinks::forReader($this->body_html, $request, $this->community_id),
            'author' => $deleted ? null : $this->author?->toResource(AuthorResource::class),
            'parent_cuid' => $this->parent_id === null ? null : ($this->relationLoaded('parent') ? $this->parent?->cuid : $this->parent()->value('cuid')),
            'reply_count' => $this->reply_count,
            'last_reply_at' => $this->last_reply_at,
            'edited_at' => $this->edited_at,
            // What the current member chose for the thread of a first-level message (#1475): following, muted, stopped, or
            // null. The controllers set it for a page of first-level messages with one query (NotificationFollowStates)
            'follow_state' => $this->when($this->relationLoaded('follow'), fn () => $this->follow?->state->value),
            'deleted_at' => $this->deleted_at,
            'reactions' => $this->when($this->relationLoaded('reactions'), fn () => $deleted ? [] : $this->reactionSummary($request->user())),
            'created_at' => $this->created_at,
        ];
    }
}
