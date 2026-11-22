<?php

namespace App\Http\Resources;

use App\Models\MemberTag;
use Illuminate\Http\Request;

/**
 * A member tag (#1389, ADR 0060). `members_count` is a number for the people who see every tag (the list of the tags
 * loads it), and null elsewhere: in the list of a member, and for a member who sees the tags of the members only.
 *
 * @mixin MemberTag
 */
class MemberTagResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with memberTagSchema (member-tag.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var MemberTag $tag */
        $tag = $this->resource;
        $count = $tag->getAttributes()['members_count'] ?? null;

        return [
            'cuid' => $this->cuid,
            'name' => $this->name,
            'color' => $this->color->value,
            'visibility' => $this->visibility->value,
            'members_count' => is_numeric($count) ? (int) $count : null,
            'created_at' => $this->created_at,
        ];
    }
}
