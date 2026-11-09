<?php

namespace App\Http\Resources;

use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\ResourceTextArticle;
use App\Models\Space;
use App\Models\UserProfile;
use Illuminate\Http\Request;

/** @mixin CommunityResource **/
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
            'resource' => $this->whenLoaded('resource', fn (Resource $resource) => $resource->toResource()->withLargeFields($this->serializeLargeFields)),
            'resource_text_article' => $this->whenLoaded('textArticle', fn (ResourceTextArticle $textArticle) => $textArticle->toResource()->withLargeFields($this->serializeLargeFields)),
            // The public summary only: never the phone, location or links of the profile (#1234)
            'curator' => $this->whenLoaded('curator', fn (?UserProfile $curator) => $curator?->toResource(AuthorResource::class)),
            'space' => $this->whenLoaded('space', fn (Space $space) => $space->toResource()),
            'is_featured' => $this->is_featured,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
