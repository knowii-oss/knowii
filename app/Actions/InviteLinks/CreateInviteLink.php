<?php

namespace App\Actions\InviteLinks;

use App\Constants;
use App\Contracts\InviteLinks\CreatesInviteLinks;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\InviteLink;
use App\Models\User;
use App\Support\InvitationGrants;
use App\Support\InviteLinks;
use App\Support\InviteLinkTexts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class CreateInviteLink implements CreatesInviteLinks
{
    /**
     * Create an invite link (#1391, ADR 0062): the owner and the admins. It is on unless the request says otherwise, and has
     * no end and no maximum unless the request sets them. The groups need the right to manage the members of the groups,
     * the tags the right to assign tags (InvitationGrants). A group that a rule on an entitlement feeds is refused. A
     * community has at most 50 links, whatever their state. The log line names the community and the link by their cuid,
     * never the name nor the token.
     */
    final public function create(User $user, Community $community, array $input): InviteLink
    {
        Gate::forUser($user)->authorize('manageInviteLinks', $community);
        InviteLinkInputRules::ensureShared($community);

        $validated = InviteLinkInputRules::validate($input, true);
        $expiresAt = InviteLinkInputRules::expiresAt($validated['expires_at'] ?? null);
        $grants = InviteLinkInputRules::grants($user, $community, $input);
        $token = InviteLink::newToken();

        $link = DB::transaction(static function () use ($community, $validated, $expiresAt, $grants, $token): InviteLink {
            // Two creates at once cannot pass the limit
            Community::query()->whereKey($community->id)->lockForUpdate()->first(['id']);
            if ($community->inviteLinks()->count() >= Constants::MAX_INVITE_LINKS_PER_COMMUNITY) {
                throw new BusinessException(InviteLinkTexts::TOO_MANY);
            }

            // Every field is set here: the answer holds them all (data rule 7)
            $link = $community->inviteLinks()->create([
                'name' => $validated['name'] ?? '',
                'token' => $token,
                'token_hash' => InviteLink::hashToken($token),
                'active' => $validated['active'] ?? true,
                'expires_at' => $expiresAt,
                'max_uses' => isset($validated['max_uses']) ? (int) $validated['max_uses'] : null,
            ]);

            // A group or a tag deleted since the read of the lists is left out
            $present = InvitationGrants::lockPresent($grants[InvitationGrants::FIELD_ACCESS_GROUPS], $grants[InvitationGrants::FIELD_MEMBER_TAGS]);
            if ($present[InvitationGrants::FIELD_ACCESS_GROUPS]->isNotEmpty()) {
                $link->accessGroups()->attach($present[InvitationGrants::FIELD_ACCESS_GROUPS]->modelKeys());
            }
            if ($present[InvitationGrants::FIELD_MEMBER_TAGS]->isNotEmpty()) {
                $link->memberTags()->attach($present[InvitationGrants::FIELD_MEMBER_TAGS]->modelKeys());
            }

            return $link;
        });

        Log::info('Invite link created', ['community' => $community->cuid, 'link' => $link->cuid]);

        $link->refresh();
        InviteLinks::loadForManagers([$link]);

        return $link;
    }
}
