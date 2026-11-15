<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * A member of an access group with the source of the membership (#1386, ADR 0047): `manual` (added by hand or by the API),
 * `by_rule` (an entitlement rule gives it), or both. Only the source: never the key that a rule uses.
 *
 * @mixin User
 */
class AccessGroupMembershipResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with accessGroupMemberSchema (access-group.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $sources = [];
        if ((bool) $user->getAttribute('membership_manual')) {
            $sources[] = 'manual';
        }
        if ((bool) $user->getAttribute('membership_by_rule')) {
            $sources[] = 'by_rule';
        }

        return [...$user->toResource(AuthorResource::class)->toArray($request), 'sources' => $sources];
    }
}
