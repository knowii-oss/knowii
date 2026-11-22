<?php

namespace App\Actions\InviteLinks;

use App\Contracts\InviteLinks\UpdatesInviteLinks;
use App\Models\InviteLink;
use App\Models\User;
use App\Support\InvitationGrants;
use App\Support\InviteLinks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class UpdateInviteLink implements UpdatesInviteLinks
{
    /**
     * Change an invite link (#1391, ADR 0062): the owner and the admins. Only the fields of the request change; `access_groups`
     * and `member_tags` replace the lists. Turning it off stops it at once, on again it works again. A maximum at or below
     * the number of joins makes it full. A change of the groups or the tags changes nothing for the people who joined
     * before.
     */
    final public function update(User $user, InviteLink $link, array $input): InviteLink
    {
        $community = $link->community;
        Gate::forUser($user)->authorize('manageInviteLinks', $community);

        $validated = InviteLinkInputRules::validate($input, false);

        $changes = [];
        if (array_key_exists('name', $validated)) {
            $changes['name'] = $validated['name'];
        }
        if (array_key_exists('active', $validated)) {
            $changes['active'] = $validated['active'];
        }
        if (array_key_exists('expires_at', $validated)) {
            $changes['expires_at'] = InviteLinkInputRules::expiresAt($validated['expires_at']);
        }
        if (array_key_exists('max_uses', $validated)) {
            $changes['max_uses'] = $validated['max_uses'] === null ? null : (int) $validated['max_uses'];
        }

        $setsGroups = array_key_exists(InvitationGrants::FIELD_ACCESS_GROUPS, $input);
        $setsTags = array_key_exists(InvitationGrants::FIELD_MEMBER_TAGS, $input);
        $grants = InviteLinkInputRules::grants($user, $community, $input);

        DB::transaction(static function () use ($link, $changes, $grants, $setsGroups, $setsTags) {
            if ($changes !== []) {
                $link->update($changes);
            }

            // A group or a tag deleted since the read of the lists is left out
            $present = InvitationGrants::lockPresent($grants[InvitationGrants::FIELD_ACCESS_GROUPS], $grants[InvitationGrants::FIELD_MEMBER_TAGS]);
            if ($setsGroups) {
                $link->accessGroups()->sync($present[InvitationGrants::FIELD_ACCESS_GROUPS]->modelKeys());
            }
            if ($setsTags) {
                $link->memberTags()->sync($present[InvitationGrants::FIELD_MEMBER_TAGS]->modelKeys());
            }
            if (($setsGroups || $setsTags) && $changes === []) {
                $link->touch();
            }
        });

        $fields = array_keys($changes);
        if ($setsGroups) {
            $fields[] = InvitationGrants::FIELD_ACCESS_GROUPS;
        }
        if ($setsTags) {
            $fields[] = InvitationGrants::FIELD_MEMBER_TAGS;
        }
        Log::info('Invite link updated', ['community' => $community->cuid, 'link' => $link->cuid, 'fields' => $fields]);

        $link->refresh();
        InviteLinks::loadForManagers([$link]);

        return $link;
    }
}
