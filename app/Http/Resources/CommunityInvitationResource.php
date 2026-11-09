<?php

namespace App\Http\Resources;

use App\Models\CommunityInvitation;
use Illuminate\Http\Request;

/**
 * A pending invitation to a community (#1242). Only members who manage members see it.
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
        ];
    }
}
