<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityResource extends JsonResource
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
          // The name used with whenLoaded is the name of a function on the CommunityResource model
          'resource' => ResourceResource::make($this->whenLoaded('resource')),
          'textArticle' => ResourceTextArticleResource::make($this->whenLoaded('textArticle')),
          'curator' => UserProfileResource::make($this->whenLoaded('curator')),
          'is_featured' => $this->is_featured,
          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
