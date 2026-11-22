<?php

namespace App\Http\Resources;

use App\Models\InvitationImportRow;
use Illuminate\Http\Request;

/**
 * One row of an import of invitations (#1390, ADR 0061): the address, the role, the names of the groups and the tags,
 * the result and the reason in words. For the people who manage the members only: an address of a row is personal data
 * of a person who is not a member.
 *
 * @mixin InvitationImportRow
 */
class InvitationImportRowResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with invitationImportRowSchema (invitation-import.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var InvitationImportRow $row */
        $row = $this->resource;

        return [
            'cuid' => $row->cuid,
            'row_number' => $row->row_number,
            'email' => $row->email,
            'role' => $row->role?->value,
            'access_groups' => $row->access_group_names,
            'member_tags' => $row->member_tag_names,
            'outcome' => $row->outcome->value,
            'reason' => $row->reason?->value,
            'reason_text' => $row->reason?->text($row->detail),
            'detail' => $row->detail,
            'still_invited' => $row->community_invitation_id !== null,
            'sent_at' => $row->sent_at,
        ];
    }
}
