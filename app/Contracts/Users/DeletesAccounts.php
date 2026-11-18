<?php

namespace App\Contracts\Users;

use App\Exceptions\BusinessException;
use App\Models\User;
use Laravel\Jetstream\Contracts\DeletesUsers;

/**
 * Deletes an account, with an option to delete the content of the member too (#1344, ADR 0043). An account without a
 * password uses the proof of a new sign-in (#1521, ADR 0052).
 */
interface DeletesAccounts extends DeletesUsers
{
    /**
     * @param  array<mixed>  $input  `delete_content` (boolean, optional): also delete the posts, comments and messages of the member
     */
    public function delete(User $user, array $input = []): void;

    /**
     * Delete an account without a password with the proof of a new sign-in at the provider (#1521, ADR 0052). The proof
     * is checked and used up in the same transaction as the deletion. The caller checks the two-factor code first.
     *
     * @param  array<mixed>  $input  `delete_content`, as for delete()
     *
     * @throws BusinessException When the account has a password, or has no valid proof
     */
    public function deleteAfterSignIn(User $user, array $input = []): void;
}
