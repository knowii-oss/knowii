<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CommunityResourceResource extends AbstractKnowiiJsonResource
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
      'name' => $this->name,
      'description' => $this->description,
      'slug' => $this->slug,
      'resource' => $this->whenLoaded('resource')? new ResourceResource($this->whenLoaded('resource'), $this->serializeLargeFields): null,
      'resource_text_article' => $this->whenLoaded('textArticle')? new ResourceTextArticleResource($this->whenLoaded('textArticle'), $this->serializeLargeFields): null,
      'curator' => $this->whenLoaded('curator')? new UserProfileResource($this->whenLoaded('curator'), $this->serializeLargeFields): null,
      'collection' => $this->whenLoaded('collection')? new CommunityResourceCollectionResource($this->whenLoaded('collection'), $this->serializeLargeFields): null,
      'is_featured' => $this->is_featured,
      'created_at' => $this->created_at,
      'updated_at' => $this->updated_at,
    ];
  }
}
