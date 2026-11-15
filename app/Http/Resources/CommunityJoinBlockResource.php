<?php

namespace App\Http\Resources;

use App\Models\CommunityJoinBlock;
use Illuminate\Http\Request;

/**
 * A person whom a manager removed from the community and who cannot join it again by themselves (#1486). For the
 * managers of the members only: the person as the member list shows them, and the date of the removal.
 *
 * WARNING: aligned with communityJoinBlockSchema (member.schema.ts)
 *
 * @mixin CommunityJoinBlock
 */
class CommunityJoinBlockResource extends AbstractKnowiiJsonResource
{
    /**
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var CommunityJoinBlock $block */
        $block = $this->resource;

        return [
            'cuid' => $block->user->cuid,
            'name' => $block->user->name,
            'username' => $block->user->username,
            'avatar_url' => $block->user->avatar_url,
            'removed_at' => $block->created_at,
        ];
    }
}
