<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;

/**
 * @mixin Post
 */
class PostResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with postSchema (post.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        $deleted = $this->isDeleted();

        return [
            'cuid' => $this->cuid,
            // A deleted post is a marker (#1344): no title, no text, no author, whatever the row holds
            'title' => $deleted ? '' : $this->title,
            'slug' => $this->slug,
            // The Markdown source is needed to edit; lists only need the HTML
            'body' => $this->when($this->serializeLargeFields, $deleted ? '' : $this->body),
            'body_html' => $deleted ? '' : $this->body_html,
            'is_pinned' => $this->is_pinned,
            'pinned_at' => $this->pinned_at,
            'edited_at' => $this->edited_at,
            // null when the author deleted their account
            'author' => $deleted ? null : $this->author?->toResource(AuthorResource::class),
            // Comments and replies, when the query counted them
            'comments_count' => $this->whenCounted('comments'),
            'space_cuid' => $this->relationLoaded('space') ? $this->space->cuid : $this->space()->value('cuid'),
            // Count per emoji and whether the current member reacted (when the reactions are loaded)
            'reactions' => $this->when($this->relationLoaded('reactions'), fn () => $deleted ? [] : $this->reactionSummary($request->user())),
            // What the current member chose for the thread (#1475): following, muted, stopped, or null. The controllers set it
            // for a whole page with one query (NotificationFollowStates); a resource without it has no such field
            'follow_state' => $this->when($this->relationLoaded('follow'), fn () => $this->follow?->state->value),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
