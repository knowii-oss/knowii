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
        return [
            'cuid' => $this->cuid,
            'title' => $this->title,
            'slug' => $this->slug,
            // The Markdown source is needed to edit; lists only need the HTML
            'body' => $this->when($this->serializeLargeFields, $this->body),
            'body_html' => $this->body_html,
            'is_pinned' => $this->is_pinned,
            'pinned_at' => $this->pinned_at,
            'edited_at' => $this->edited_at,
            // null when the author deleted their account
            'author' => $this->author?->toResource(AuthorResource::class),
            // Comments and replies, when the query counted them
            'comments_count' => $this->whenCounted('comments'),
            'space_cuid' => $this->relationLoaded('space') ? $this->space->cuid : $this->space()->value('cuid'),
            // Count per emoji and whether the current member reacted (when the reactions are loaded)
            'reactions' => $this->when($this->relationLoaded('reactions'), fn () => $this->reactionSummary($request->user())),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
