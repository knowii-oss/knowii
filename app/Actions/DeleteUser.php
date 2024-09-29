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
            // Remove the user's communities and associated elements
            $this->deleteCommunities($user);

            // When a user deletes their account, their user profile remains available, but is not associated with them anymore
            // This ensures that associated resources are not lost
            $user->detachProfile();

            // Remove tokens
            $user->tokens->each->delete();

            // Remove the user account
            $user->delete();
        });
        Log::info("User deleted", ['user' => $user]);
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
