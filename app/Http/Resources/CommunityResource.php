<?php

namespace App\Http\Resources;

use App\Models\Community;
use Illuminate\Http\Request;

/** @mixin Community **/
class CommunityResource extends AbstractKnowiiJsonResource
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
            'slug' => $this->slug,
            'description' => $this->description ?? '', // Ensures the returned value is never null
            'visibility' => $this->visibility,
            // Every right with its value, also the defaults (#1240, ADR 0017)
            'moderator_permissions' => $this->moderatorPermissions(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
