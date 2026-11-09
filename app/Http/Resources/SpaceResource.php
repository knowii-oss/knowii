<?php

namespace App\Http\Resources;

use App\Models\Space;
use Illuminate\Http\Request;

/**
 * @mixin Space
 */
class SpaceResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: The fields MUST remain aligned with spaceSchema (space.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
            'cuid' => $this->cuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?? '', // Ensures the returned value is never null
            'emoji' => $this->emoji ?? '', // Ensures the returned value is never null
            'type' => $this->type,
            'visibility' => $this->visibility,
            'posting_policy' => $this->posting_policy,
            'position' => $this->position,
            'space_group_cuid' => $this->relationLoaded('spaceGroup') ? $this->spaceGroup->cuid : $this->spaceGroup()->value('cuid'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
