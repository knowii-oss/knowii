<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityResourceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
          'cuid' => $this->cuid,
          'slug' => $this->slug,
          'resource' => ResourceResource::make($this->whenLoaded('resource')),
          'resource_text_article' => ResourceTextArticleResource::make($this->whenLoaded('textArticle')),
          'curator' => UserProfileResource::make($this->whenLoaded('curator')),
          'collection' => CommunityResourceCollectionResource::make($this->whenLoaded('collection')),
          'is_featured' => $this->is_featured,
          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
