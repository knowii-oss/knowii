<?php

namespace App\Contracts\Users;

use App\Models\User;
use Laravel\Jetstream\Contracts\DeletesUsers;

/**
 * Deletes an account, with an option to delete the content of the member too (#1344, ADR 0043).
 */
interface DeletesAccounts extends DeletesUsers
{
    /**
     * @param  array<mixed>  $input  `delete_content` (boolean, optional): also delete the posts, comments and messages of the member
     */
    public function delete(User $user, array $input = []): void;
}
