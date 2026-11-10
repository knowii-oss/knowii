<?php

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;

/**
 * @mixin Comment
 */
class CommentResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with commentSchema (comment.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
            'cuid' => $this->cuid,
            'body' => $this->body,
            'body_html' => $this->body_html,
            'edited_at' => $this->edited_at,
            'author' => $this->author?->toResource(AuthorResource::class),
            'parent_cuid' => $this->parent_id === null ? null : ($this->relationLoaded('parent') ? $this->parent?->cuid : $this->parent()->value('cuid')),
            'replies' => self::collection($this->whenLoaded('replies')),
            // Count per emoji and whether the current member reacted (when the reactions are loaded)
            'reactions' => $this->when($this->relationLoaded('reactions'), fn () => $this->reactionSummary($request->user())),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
