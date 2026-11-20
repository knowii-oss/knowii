<?php

namespace App\Listeners;

use App\Http\Middleware\EndOutdatedSessions;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Every sign-in of a session writes the version of the sessions of the account into the session (ADR 0059): the form with
 * the password, the code of the second factor, a sign-in link, the provider, a new account, and the remember cookie (the
 * guard fires Login for it too). EndOutdatedSessions compares it with the version of the account at each request.
 */
class StoreSessionVersion
{
    final public function handle(Login $event): void
    {
        $request = request();
        if ($event->guard !== 'web' || ! $event->user instanceof User || ! $request->hasSession()) {
            return;
        }

        // The session of the request is the store of the session guard
        $request->session()->put(EndOutdatedSessions::SESSION_KEY, $event->user->sessionVersion());
    }
}
