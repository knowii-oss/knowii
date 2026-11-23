<?php

namespace App\Actions\CommunityMembers;

use App\Contracts\CommunityMembers\InvitesCommunityMembers;
use App\Contracts\Mail\ChecksMailBudgets;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Exceptions\BusinessException;
use App\Mail\CommunityInvitation as CommunityInvitationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use App\Support\CommunityRoles;
use App\Support\InvitationGrants;
use App\Support\InvitationMailCaps;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InviteCommunityMember implements InvitesCommunityMembers
{
    public const string CANNOT_REPLACE = 'This address has an invitation with a role that you cannot give';

    public const string MAIL_HELD = 'This instance holds its mail now. Invite this person from :time UTC.';

    /**
     * Invite an email address with a role lower than one's own (#1242). Inviting the same address again replaces the
     * invitation and sends a new email. A manager replaces an invitation that has not expired only when they may give its
     * role, as for a cancel. The invitation can carry access groups and member tags, given at the join (#1390, ADR
     * 0061): the new invitation has the lists of this request only, less a group or a tag deleted meanwhile. A personal
     * community has no other member: it invites nobody.
     *
     * The order of the refusals: the email and the role (400), the role hierarchy (403), a personal community (422), a
     * member (400), the groups and the tags (InvitationGrants: 400 or 403), the role of the invitation that the new one
     * replaces (403), a hold of the mail budget (422, with its end), then the daily caps of the invitation mails of the
     * community, of the account and of the instance (422, with the time from when it works again: InvitationMailCaps).
     * The mail goes out at once, never held (ADR 0054 point 1): a single invitation waits for the end of a hold of the
     * mail budget.
     *
     * The transaction takes the locks of the daily caps of the community and of the account first (the community, then
     * the account, as the confirm of an import), then reads and locks the invitations of the address: an import of the
     * same address that confirms now ends before that read. The lock of the instance comes last, after the hold, just
     * before the counts (InvitationMailCaps::assertRoom()).
     *
     * @throws AuthorizationException
     * @throws BusinessException
     * @throws ValidationException
     */
    final public function invite(User $user, Community $community, array $input): CommunityInvitation
    {
        Gate::forUser($user)->authorize('addCommunityMember', $community);

        /** @var array{email: string, role: string} $validated */
        $validated = Validator::make($input, [
            'email' => ['required', 'string', 'email:strict', 'max:255'],
            'role' => ['required', 'string', Rule::enum(KnowiiCommunityMemberRole::class)],
        ])->validate();
        $email = mb_strtolower(trim($validated['email']));
        $role = KnowiiCommunityMemberRole::from($validated['role']);

        if (! CommunityRoles::canGrant($user, $community, $role)) {
            throw new AuthorizationException('You cannot give this role');
        }

        if ($community->visibility === KnowiiCommunityVisibility::Personal) {
            throw new BusinessException('A personal community cannot invite anyone');
        }

        if ($community->hasUserWithEmail($email)) {
            throw ValidationException::withMessages(['email' => 'This person is already a member of the community']);
        }

        ['access_groups' => $groups, 'member_tags' => $tags] = InvitationGrants::read($user, $community, $input);

        /** @var CommunityInvitation $invitation */
        [$invitation, $groups, $tags] = DB::transaction(static function () use ($user, $community, $email, $role, $groups, $tags): array {
            InvitationMailCaps::lock($user, $community);
            $previous = $community->communityInvitations()->whereRaw('lower(email) = ?', [$email])->lockForUpdate()->get();
            foreach ($previous as $old) {
                if (! $old->isExpired() && ! CommunityRoles::canGrant($user, $community, $old->role)) {
                    throw new AuthorizationException(self::CANNOT_REPLACE);
                }
            }
            // A single invitation is never held: while the budget holds mail, and past a daily cap, it is refused
            $heldUntil = app(ChecksMailBudgets::class)->heldUntil();
            if ($heldUntil !== null) {
                throw new BusinessException(str_replace(':time', $heldUntil->utc()->ceilMinute()->format('Y-m-d H:i'), self::MAIL_HELD));
            }
            InvitationMailCaps::assertRoom($user, $community, 1, single: true);
            $community->communityInvitations()->whereKey($previous->modelKeys())->delete();

            // A group or a tag deleted since the read of the lists is skipped
            ['access_groups' => $groups, 'member_tags' => $tags] = InvitationGrants::lockPresent($groups, $tags);

            /** @var CommunityInvitation $invitation */
            $invitation = $community->communityInvitations()->create(['email' => $email, 'role' => $role]);
            InvitationGrants::attach($invitation, $groups, $tags);
            InvitationMailCaps::record($user, $community, 1);

            return [$invitation, $groups, $tags];
        });

        Mail::to($email)->queue((new CommunityInvitationMail($invitation, $user))->recordDelivery(invitation: $invitation));

        Log::info('Community invitation sent', [
            'community' => $community->cuid,
            'invitation' => $invitation->cuid,
            'role' => $role->value,
            'access_groups' => $groups->count(),
            'member_tags' => $tags->count(),
        ]);
        CommunityMembersChanged::dispatch($community->cuid);

        return $invitation;
    }
}
