<?php

namespace App\Contracts\Users;

use App\Models\User;

interface VerifiesUsernameAvailability
{
    /**
     * @param  array<string,string>  $input
     */
    public function verify(User $user, array $input): bool;
}
