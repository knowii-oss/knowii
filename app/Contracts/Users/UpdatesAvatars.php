<?php

namespace App\Contracts\Users;

use App\Models\User;

interface UpdatesAvatars
{
    /**
     * Replace the avatar of a user with the uploaded image (`file`).
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, array $input): User;

    /**
     * Remove the avatar of a user: the initials show again.
     */
    public function delete(User $user): User;
}
