<?php

namespace App\Http\Resources;

use App\Models\Page;
use App\Models\User;
use App\Support\MentionLinks;
use Illuminate\Http\Request;

/**
 * A page of a page space (#1179). The Markdown and its HTML are large fields: lists carry the titles only.
 *
 * @mixin Page
 */
class PageResource extends AbstractKnowiiJsonResource
{
    /**
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
            'cuid' => $this->cuid,
            // A page (a lesson in a course) or a section heading (#1188)
            'kind' => $this->kind,
            'title' => $this->title,
            'slug' => $this->slug,
            'position' => $this->position,
            'body' => $this->serializeLargeFields ? $this->body : null,
            // A mention is a link for a member of the community only (MentionLinks)
            'body_html' => $this->serializeLargeFields ? MentionLinks::forReader($this->body_html, $request, $this->community_id) : null,
            // Lessons of a course space (#1188): the provider and the player address; null without a video
            'video' => $this->video(),
            // Lessons of a course space (#1335): whether the lesson has a quiz (get it from its own endpoint)
            'has_quiz' => $this->hasQuiz(),
            'author' => $this->whenLoaded('author', fn (?User $author) => $author?->toResource(AuthorResource::class)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
