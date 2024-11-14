<?php

namespace App\Contracts\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

/**
 * @method Model verify(User $user, array $input)
 */
interface VerifiesUsernameAvailability
{
    //
}
