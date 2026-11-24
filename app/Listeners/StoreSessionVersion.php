<?php

namespace App\Listeners;

use App\Http\Middleware\EndOutdatedSessions;
use App\Models\User;
use App\Support\RequestCredential;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Every sign-in of a session writes the version of the sessions of the account into the session (ADR 0059): the form with
 * the password, the code of the second factor, a sign-in link, the provider, a new account, and the remember cookie (the
 * guard fires Login for it too). EndOutdatedSessions compares it with the version of the account at each request.
 *
 * It also writes how the session began (ADR 0067): a session that the "Remember me" cookie made holds
 * RequestCredential::RECALLED_SESSION_KEY, because nobody typed a password for it. Every other sign-in removes the key.
 * App\Support\PasswordTries reads it.
 */
class StoreSessionVersion
{
    /**
     * The attribute of a request whose sign-in by the "Remember me" cookie was seen
     */
    private const string RECALL_SEEN = 'knowii.remember_cookie_sign_in_seen';

    final public function handle(Login $event): void
    {
        $request = request();
        if ($event->guard !== 'web' || ! $event->user instanceof User || ! $request->hasSession()) {
            return;
        }

        // The session of the request is the store of the session guard
        $session = $request->session();
        $session->put(EndOutdatedSessions::SESSION_KEY, $event->user->sessionVersion());

        if (self::isByRememberCookie($request)) {
            $session->put(RequestCredential::RECALLED_SESSION_KEY, true);
        } else {
            $session->forget(RequestCredential::RECALLED_SESSION_KEY);
        }
    }

    /**
     * Whether this sign-in is the one of the "Remember me" cookie. The guard says that the cookie signed the request in,
     * and fires Login for it at once: the first Login of such a request. A Login that comes later in the same request is a
     * sign-in that the request made itself
     */
    private static function isByRememberCookie(Request $request): bool
    {
        if (! Auth::guard('web')->viaRemember() || $request->attributes->get(self::RECALL_SEEN) === true) {
            return false;
        }
        $request->attributes->set(self::RECALL_SEEN, true);

        return true;
    }
}
