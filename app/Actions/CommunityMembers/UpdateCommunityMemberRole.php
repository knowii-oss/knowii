<?php

namespace App\Actions\CommunityMembers;

use App\Contracts\CommunityMembers\UpdatesCommunityMemberRoles;
use App\Enums\KnowiiCommunityMemberRole;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Models\Community;
use App\Models\User;
use App\Support\CommunityRoles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateCommunityMemberRole implements UpdatesCommunityMemberRoles
{
    use FindsCommunityMembers;

    /**
     * Change the role of a member, within the role hierarchy (#1242, ADR 0017): never the owner, never a member with
     * the same role or a higher one, never to a role as high as one's own.
     *
     * @throws AuthorizationException
     */
    final public function update(User $user, Community $community, string $memberCuid, array $input): User
    {
        Gate::forUser($user)->authorize('updateCommunityMember', $community);

        /** @var array{role: string} $validated */
        $validated = Validator::make($input, [
            'role' => ['required', 'string', Rule::enum(KnowiiCommunityMemberRole::class)],
        ])->validate();
        $role = KnowiiCommunityMemberRole::from($validated['role']);

        [$target, $currentRole] = $this->findMember($community, $memberCuid);

        if (! CommunityRoles::canActOn($user, $community, $currentRole)) {
            throw new AuthorizationException('You cannot change the role of this member');
        }
        if (! CommunityRoles::canGrant($user, $community, $role)) {
            throw new AuthorizationException('You cannot give this role');
        }

        $community->users()->updateExistingPivot($target->id, ['role' => $role]);

        Log::info('Community member role changed', ['community' => $community->cuid, 'member' => $target->cuid, 'role' => $role->value]);
        CommunityMembersChanged::dispatch($community->cuid);

        return $target;
    }
}
