<?php

namespace App\Contracts\Communities;

use App\Models\Community;
use App\Models\User;

interface DeletesCommunities
{
    /**
     * Delete the community for the person who may delete it (the deletion of an account deletes the communities that
     * the account owns this way)
     */
    public function delete(User $user, Community $community): void;

    /**
     * Delete the community on the request of its owner: `name` in the input must be the name of the community
     *
     * @param  array<mixed>  $input
     */
    public function deleteWithName(User $user, Community $community, array $input): void;
}
