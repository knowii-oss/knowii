<?php

namespace App\Contracts\Communities;

use App\Models\User;

interface VerifiesCommunityNameAvailability
{
    /**
     * Whether a shared community can take this name (#1136, ADR 0033). `community` names the community that is renamed.
     *
     * @param  array<mixed>  $input
     */
    public function verify(User $user, array $input): bool;
}
