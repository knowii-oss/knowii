<?php

namespace App\Actions\CommunityMembers;

use App\Contracts\CommunityMembers\InvitesCommunityMembers;
use App\Enums\KnowiiCommunityMemberRole;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Mail\CommunityInvitation as CommunityInvitationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use App\Support\CommunityRoles;
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
    /**
     * Invite an email address with a role lower than one's own (#1242). Inviting the same address again replaces the
     * invitation and sends a new email.
     *
     * @throws AuthorizationException
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

        if ($community->hasUserWithEmail($email)) {
            throw ValidationException::withMessages(['email' => 'This person is already a member of the community']);
        }

        $invitation = DB::transaction(static function () use ($community, $email, $role) {
            $community->communityInvitations()->whereRaw('lower(email) = ?', [$email])->delete();

            /** @var CommunityInvitation $invitation */
            $invitation = $community->communityInvitations()->create(['email' => $email, 'role' => $role]);

            return $invitation;
        });

        Mail::to($email)->queue(new CommunityInvitationMail($invitation));

        Log::info('Community invitation sent', ['community' => $community->cuid, 'invitation' => $invitation->cuid, 'role' => $role->value]);
        CommunityMembersChanged::dispatch($community->cuid);

        return $invitation;
    }
}
