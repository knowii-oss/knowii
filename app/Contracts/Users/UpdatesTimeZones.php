<?php

namespace App\Contracts\Users;

use App\Models\User;

interface UpdatesTimeZones
{
    /**
     * Set the time zone of the account (#1476): `time_zone`, an IANA name that PHP knows (`Europe/Brussels`, `UTC`). Only
     * a request of the member calls it: the platform never changes the zone by itself.
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, array $input): User;
}
