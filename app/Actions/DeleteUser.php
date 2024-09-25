<?php

namespace App\Actions;

use App\Contracts\Communities\DeletesCommunities;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Illuminate\Support\Facades\Log;

class DeleteUser implements DeletesUsers
{
    /**
     * Create a new action instance.
     */
    public function __construct(protected DeletesCommunities $deletesCommunities)
    {
    }

    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        Log::info("Deleting user", ['user' => $user]);
        DB::transaction(function () use ($user) {
            $this->deleteCommunities($user);
            $user->deleteProfilePhoto();
            $user->tokens->each->delete();
            $user->delete();
        });
    }

    /**
     * Delete the communities and community associations attached to the user.
     */
    protected function deleteCommunities(User $user): void
    {
        // Remove memberships
        $user->communities()->detach();

        // Remove owned communities
        $user->ownedCommunities->each(function (Community $community) {
            $this->deletesCommunities->delete($community);
        });
    }
}
