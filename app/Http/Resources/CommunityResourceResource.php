<?php

namespace App\Http\Resources;

use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\ResourceCapture;
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
            // ADR 0018: what the community decided and the state of the capture
            'type' => $this->type->value,
            // The curator chose the type: a new capture keeps it (#1246)
            'type_chosen' => $this->type_chosen,
            'level' => $this->level->value,
            'capture_status' => $this->capture_status->value,
            'capture_failure_reason' => $this->capture_failure_reason,
            // The captured content: loaded for the single resource only, the Markdown with withLargeFields() only
            'capture' => $this->whenLoaded('capture', fn (?ResourceCapture $capture) => $capture?->toResource()->withLargeFields($this->serializeLargeFields)),
            // The public summary only: never the phone, location or links of the profile (#1234)
            'curator' => $this->whenLoaded('curator', fn (?UserProfile $curator) => $curator?->toResource(AuthorResource::class)),
            'space' => $this->whenLoaded('space', fn (Space $space) => $space->toResource()),
            'is_featured' => $this->is_featured,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
