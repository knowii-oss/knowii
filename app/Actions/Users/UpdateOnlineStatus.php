<?php

namespace App\Actions\Users;

use App\Contracts\Users\UpdatesOnlineStatus;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Show or hide when a member is online (#1187, ADR 0027). Hidden, the member never joins a presence channel: the
 * pages leave them at once, and the channel authorization refuses a new join.
 */
class UpdateOnlineStatus implements UpdatesOnlineStatus
{
    final public function update(User $user, array $input): User
    {
        /** @var array{show_online_status: bool} $validated */
        $validated = Validator::make($input, [
            'show_online_status' => ['required', 'boolean'],
        ])->validate();

        $user->forceFill(['show_online_status' => (bool) $validated['show_online_status']])->save();

        Log::info('Online status changed', ['user' => $user->cuid, 'shown' => (bool) $validated['show_online_status']]);

        return $user;
    }
}
