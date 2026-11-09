<?php

namespace App\Contracts\Auth;

use App\Models\User;

interface ConsumesMagicLinks
{
    /**
     * The user of a valid link, without using it up (the confirmation page shows the address), or null.
     */
    public function peek(string $token): ?User;

    /**
     * The user of a valid link, which is used up at once, or null.
     */
    public function consume(string $token): ?User;
}
