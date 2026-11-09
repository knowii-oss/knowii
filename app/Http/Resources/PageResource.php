<?php

namespace App\Http\Resources;

use App\Models\Page;
use App\Models\User;
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
            'title' => $this->title,
            'slug' => $this->slug,
            'position' => $this->position,
            'body' => $this->serializeLargeFields ? $this->body : null,
            'body_html' => $this->serializeLargeFields ? $this->body_html : null,
            'author' => $this->whenLoaded('author', fn (?User $author) => $author?->toResource(AuthorResource::class)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
