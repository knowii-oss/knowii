<?php

namespace App\Actions;

use App\Contracts\Communities\DeletesCommunities;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Illuminate\Support\Facades\Log;

class DeleteUser implements DeletesUsers
{
    /**
     * Create a new action instance.
     */
    public function __construct(private readonly DeletesCommunities $deletesCommunities)
    {
    }

    /**
     * Delete the given user.
     */
    final public function delete(User $user): void
    {
        Log::info("Deleting user", ['user' => $user]);
        DB::transaction(function () use ($user) {
            $this->deleteUserProfile($user);
            $this->deleteCommunities($user);
            $user->tokens->each->delete();
            $user->delete();
        });
        Log::info("User deleted", ['user' => $user]);
    }

        /**
     * Delete the user's profile.
     */
    final public function deleteUserProfile(User $user): void
    {
        $userProfile = $user->profile;
        $userProfile->deleteProfilePhoto();
        $userProfile->delete();
    }

    /**
     * Delete the communities and community associations attached to the user.
     */
    final public function deleteCommunities(User $user): void
    {
        // Remove memberships
        $user->communities()->detach();

        // Remove owned communities
        $ownedCommunities = $user->ownedCommunities;

        foreach ($ownedCommunities as $community) {
            $this->deletesCommunities->delete($user, $community->cuid);
        }
    }
}
