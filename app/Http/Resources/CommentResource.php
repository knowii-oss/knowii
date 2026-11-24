<?php

namespace App\Http\Resources;

use App\Models\Comment;
use App\Support\MentionLinks;
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
        $deleted = $this->isDeleted();

        return [
            'cuid' => $this->cuid,
            // A deleted comment is a marker (#1344): no text, no author, whatever the row holds
            'body' => $deleted ? '' : $this->body,
            // A mention is a link for a member of the community only (MentionLinks)
            'body_html' => $deleted ? '' : MentionLinks::forReader($this->body_html, $request, $this->community_id),
            'edited_at' => $this->edited_at,
            'author' => $deleted ? null : $this->author?->toResource(AuthorResource::class),
            'parent_cuid' => $this->parent_id === null ? null : ($this->relationLoaded('parent') ? $this->parent?->cuid : $this->parent()->value('cuid')),
            'replies' => self::collection($this->whenLoaded('replies')),
            // Count per emoji and whether the current member reacted (when the reactions are loaded)
            'reactions' => $this->when($this->relationLoaded('reactions'), fn () => $deleted ? [] : $this->reactionSummary($request->user())),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
