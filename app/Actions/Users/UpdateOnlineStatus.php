<?php

namespace App\Actions\Users;

use App\Contracts\Users\UpdatesOnlineStatus;
use App\Models\User;
use App\Support\LiveConnections;
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

        $shownBefore = $user->showsOnlineStatus();
        $user->forceFill(['show_online_status' => (bool) $validated['show_online_status']])->save();

        Log::info('Online status changed', ['user' => $user->cuid, 'shown' => (bool) $validated['show_online_status']]);

        // Hidden at once on every page and device of the member: their live connections end, and the presence channels refuse
        // them when they connect again (ADR 0027, ADR 0075)
        if ($shownBefore && ! $user->showsOnlineStatus()) {
            LiveConnections::endFor($user);
        }

        return $user;
    }
}
