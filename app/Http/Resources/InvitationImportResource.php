<?php

namespace App\Http\Resources;

use App\Models\AccessGroup;
use App\Models\InvitationImport;
use App\Models\MemberTag;
use App\Support\InvitationImports\InvitationImportCounts;
use App\Support\InvitationImports\InvitationImports;
use Illuminate\Http\Request;

/**
 * An import of invitations from a file (#1390, ADR 0061), for the people who manage the members. The counts come from
 * InvitationImportCounts (one grouped query for a page); the defaults are the groups and the tags that still exist, by
 * name, from the relations `defaultAccessGroups` and `defaultMemberTags` that the controller sets.
 *
 * @mixin InvitationImport
 */
class InvitationImportResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with invitationImportSchema (invitation-import.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var InvitationImport $import */
        $import = $this->resource;
        $counts = $import->rowCounts ?? array_fill_keys(InvitationImportCounts::KEYS, 0);
        $user = $import->relationLoaded('user') ? $import->user : null;
        /** @var iterable<int, AccessGroup> $groups */
        $groups = $import->relationLoaded('defaultAccessGroups') ? $import->getRelation('defaultAccessGroups') : [];
        /** @var iterable<int, MemberTag> $tags */
        $tags = $import->relationLoaded('defaultMemberTags') ? $import->getRelation('defaultMemberTags') : [];

        return [
            'cuid' => $import->cuid,
            'state' => $import->state->value,
            'default_role' => $import->default_role->value,
            'default_access_groups' => collect($groups)->map(static fn (AccessGroup $group): array => ['cuid' => $group->cuid, 'name' => $group->name])->values()->all(),
            'default_member_tags' => collect($tags)->map(static fn (MemberTag $tag) => $tag->toResource())->values()->all(),
            'ignored_columns' => $import->ignored_columns,
            'counts' => $counts,
            'started_by' => $user?->toResource(AuthorResource::class),
            'created_at' => $import->created_at,
            'confirmed_at' => $import->confirmed_at,
            'finished_at' => $import->finished_at,
            'resent_at' => $import->resent_at,
            'can_resend_not_joined_from' => InvitationImports::resendNotJoinedFrom($import),
            'kept_until' => InvitationImports::keptUntil($import),
        ];
    }
}
