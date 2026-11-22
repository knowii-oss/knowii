<?php

namespace App\Http\Resources;

use App\Models\InviteLinkUse;
use Illuminate\Http\Request;

/**
 * One join through an invite link (#1391, ADR 0062), for the owner and the admins only: the member as an author, and the
 * date of the join.
 *
 * @mixin InviteLinkUse
 */
class InviteLinkUseResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with inviteLinkUseSchema (invite-link.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var InviteLinkUse $use */
        $use = $this->resource;

        return [
            'member' => $use->user->toResource(AuthorResource::class),
            'joined_at' => $this->created_at,
        ];
    }
}
