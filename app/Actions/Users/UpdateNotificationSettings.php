<?php

namespace App\Actions\Users;

use App\Contracts\Users\UpdatesNotificationSettings;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * The switch "Show pop-ups" of the account (#1476, design 5.3.5)
 */
class UpdateNotificationSettings implements UpdatesNotificationSettings
{
    final public function update(User $user, array $input): User
    {
        /** @var array{popups: bool} $validated */
        $validated = Validator::make($input, [
            'popups' => ['required', 'boolean'],
        ])->validate();

        $user->forceFill(['notification_popups' => (bool) $validated['popups']])->save();

        Log::info('Notification pop-ups changed', ['user' => $user->cuid, 'popups' => (bool) $validated['popups']]);

        return $user;
    }
}
