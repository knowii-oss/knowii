<?php

namespace App\Http\Resources;

use App\Models\AccessGroup;
use Illuminate\Http\Request;

/**
 * @mixin AccessGroup
 */
class AccessGroupResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with accessGroupSchema (access-group.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
            'cuid' => $this->cuid,
            'name' => $this->name,
            'description' => $this->description ?? '',
            'members_count' => $this->whenCounted('members'),
            'spaces_count' => $this->whenCounted('spaces'),
            'created_at' => $this->created_at,
        ];
    }
}
