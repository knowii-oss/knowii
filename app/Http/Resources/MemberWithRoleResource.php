<?php

namespace App\Http\Resources;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A member of a community with their role (#1242). Not named CommunityMemberResource: it wraps a User, not the
 * CommunityMember pivot. The user comes from CommunityMemberQuery, which adds
 * `community_role` and `community_member_since`.
 *
 * @mixin User
 */
class MemberWithRoleResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with communityMemberSchema (member.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        $role = $this->getAttribute('community_role');
        $memberSince = $this->getAttribute('community_member_since');

        return [
            'cuid' => $this->cuid,
            'name' => $this->name,
            'username' => $this->username,
            'role' => is_string($role) ? $role : '',
            'member_since' => is_string($memberSince) || $memberSince instanceof DateTimeInterface ? Carbon::parse($memberSince) : null,
        ];
    }
}
