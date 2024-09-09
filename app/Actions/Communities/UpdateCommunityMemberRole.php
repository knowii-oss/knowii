<?php

namespace App\Actions\Communities;

use App\Events\Communities\CommunityMemberUpdated;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Rules\Role;

class UpdateCommunityMemberRole
{
    /**
     * Update the role for the given member.
     *
     * @param  User  $user
     * @param  Community $community
     * @param  int  $communityMemberId
     * @param  string  $role
     * @return void
     */
    public function update(User $user, Community $community, int $communityMemberId, string $role): void
    {
        Gate::forUser($user)->authorize('updateCommunityMember', $community);

        Validator::make([
            'role' => $role,
        ], [
            'role' => ['required', 'string', new Role],
        ])->validate();

        $community->users()->updateExistingPivot($communityMemberId, [
            'role' => $role,
        ]);

        CommunityMemberUpdated::dispatch($community->fresh(), Jetstream::findUserByIdOrFail($communityMemberId));
    }
}
