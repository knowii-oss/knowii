<?php

namespace App\Actions;

use App\Contracts\Communities\DeletesCommunities;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesUsers;

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
        DB::transaction(function () use ($user) {
            // FIXME should this be the default behavior if a community owner deletes his account?
            $this->deleteCommunities($user);
            // TODO delete everything the user owns/is part of before deleting it
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
        $user->communities()->detach();

        $user->ownedCommunities->each(function (Community $community) {
            $this->deletesCommunities->delete($community);
        });
    }
}
