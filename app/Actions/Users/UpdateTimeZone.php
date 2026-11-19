<?php

namespace App\Actions\Users;

use App\Contracts\Users\UpdatesTimeZones;
use App\Models\User;
use App\Rules\KnownTimeZone;
use App\Support\TimeZones;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Set the time zone of the account (#1476, ADR 0048, design 6.7). Only a request of the member reaches it: the sign-up
 * form, `PUT /api/v1/user/time-zone` and the profile form. The platform never changes the zone by itself.
 */
class UpdateTimeZone implements UpdatesTimeZones
{
    final public function update(User $user, array $input): User
    {
        /** @var array{time_zone: string} $validated */
        $validated = Validator::make($input, [
            'time_zone' => ['required', 'string', new KnownTimeZone],
        ])->validate();

        // An older name of a zone is stored under its current name (#1678)
        $user->forceFill(['time_zone' => TimeZones::current($validated['time_zone'])])->save();

        Log::info('Time zone changed', ['user' => $user->cuid]);

        return $user;
    }
}
