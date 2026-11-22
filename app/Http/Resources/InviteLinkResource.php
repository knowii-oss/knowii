<?php

namespace App\Http\Resources;

use App\Models\AccessGroup;
use App\Models\InviteLink;
use Illuminate\Http\Request;

/**
 * An invite link (#1391, ADR 0062), for the owner and the admins only. `url` is the address to share, with the token.
 * Never the hash of the token. The groups and the tags are those that InviteLinks::loadForManagers() loaded, by name;
 * `fed_by_rule` marks a group that a rule on an entitlement feeds: the link does not give it.
 *
 * @mixin InviteLink
 */
class InviteLinkResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with inviteLinkSchema (invite-link.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var InviteLink $link */
        $link = $this->resource;

        return [
            'cuid' => $this->cuid,
            'name' => $this->name,
            'url' => $link->url(),
            'status' => $link->status()->value,
            'active' => $this->active,
            'expires_at' => $this->expires_at,
            'max_uses' => $this->max_uses,
            'uses_count' => $this->uses_count,
            'access_groups' => $link->relationLoaded('accessGroups')
                ? $link->accessGroups->map(static fn (AccessGroup $group): array => [
                    'cuid' => $group->cuid,
                    'name' => $group->name,
                    'fed_by_rule' => (bool) ($group->getAttributes()['rules_exists'] ?? false),
                ])->values()->all()
                : [],
            'member_tags' => $link->relationLoaded('memberTags') ? $link->memberTags->toResourceCollection() : [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
