<?php

namespace App\Http\Resources;

use App\Models\SpaceGroup;
use Illuminate\Http\Request;

/**
 * @mixin SpaceGroup
 */
class SpaceGroupResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: The fields MUST remain aligned with spaceGroupSchema (space.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
            'cuid' => $this->cuid,
            'name' => $this->name,
            'position' => $this->position,
            // Only the spaces that the current user can see (see SpaceTree)
            'spaces' => SpaceResource::collection($this->whenLoaded('spaces')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
