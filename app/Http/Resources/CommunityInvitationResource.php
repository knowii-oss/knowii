<?php

namespace App\Http\Resources;

use App\Models\AccessGroup;
use App\Models\CommunityInvitation;
use Illuminate\Http\Request;

/**
 * A pending invitation to a community (#1242). Only members who manage members see it. Its access groups and member
 * tags (#1390) are those that InvitationGrants::loadFor() loaded for the reader, by name: an empty list when the reader
 * may not see them.
 *
 * @mixin CommunityInvitation
 */
class CommunityInvitationResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with communityInvitationSchema (member.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        return [
            'cuid' => $this->cuid,
            'email' => $this->email,
            'role' => $this->role->value,
            'created_at' => $this->created_at,
            'expires_at' => $this->expiresAt(),
            'is_expired' => $this->isExpired(),
            'access_groups' => $this->relationLoaded('accessGroups')
                ? $this->accessGroups->map(static fn (AccessGroup $group): array => ['cuid' => $group->cuid, 'name' => $group->name])->values()->all()
                : [],
            'member_tags' => $this->relationLoaded('memberTags') ? $this->memberTags->toResourceCollection() : [],
        ];
    }
}
