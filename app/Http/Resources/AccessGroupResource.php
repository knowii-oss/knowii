<?php

namespace App\Http\Resources;

use App\Models\AccessGroup;
use App\Models\SpaceGroup;
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
            'space_groups_count' => $this->whenCounted('spaceGroups'),
            // The space groups that the group opens whole (#1422, ADR 0041)
            'space_groups' => $this->whenLoaded('spaceGroups', fn () => $this->spaceGroups->map(fn (SpaceGroup $group) => ['cuid' => $group->cuid, 'name' => $group->name])->values()->all()),
            // The keys that give the group (#1386, ADR 0047): only for the people who manage the groups, in a community that may use rules
            'entitlement_keys' => $this->whenLoaded('rules', fn () => $this->rules->pluck('entitlement_key')->sort()->values()->all()),
            'created_at' => $this->created_at,
        ];
    }
}
