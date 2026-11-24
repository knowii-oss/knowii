<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Sanctum\Contracts\HasAbilities;
use Laravel\Sanctum\TransientToken;

/**
 * The credential that signed a request in: the session of a browser, or an API token. A count for a credential (the tries
 * of the current password, the limits of the routes that ask for it) is never a count for the account as a whole: what the
 * tokens of an account do changes nothing for a session of the account, and what one session does changes nothing for a
 * session that signed in.
 *
 * A session knows how it began: a sign-in (the form with the password, the API sign-in, a sign-in link, the provider, the
 * code of the second factor), or the "Remember me" cookie of the browser, which signs in without a password
 * (App\Listeners\StoreSessionVersion writes it into the session).
 */
final class RequestCredential
{
    /**
     * The key of the session that says that the "Remember me" cookie made this session: true, or not there
     */
    public const string RECALLED_SESSION_KEY = 'signed_in_by_remember_cookie';

    /**
     * Whether the account of the request comes from a session: not from an API token
     */
    public static function isSession(Request $request, User $user): bool
    {
        return self::tokenOf($user) === null && $request->hasSession();
    }

    /**
     * Whether the account of the request comes from an API token
     */
    public static function isToken(User $user): bool
    {
        return self::tokenOf($user) !== null;
    }

    /**
     * Whether the account of the request comes from a session that the "Remember me" cookie made: nobody typed a password,
     * or made another proof, for this session
     */
    public static function isRecalledSession(Request $request, User $user): bool
    {
        return self::isSession($request, $user) && $request->session()->get(self::RECALLED_SESSION_KEY) === true;
    }

    /**
     * The key of the credential, for a counter: the id of the token, or the id of the session, hashed, so that no cache
     * holds a value that signs in. A request with neither is not the request of a browser or of an API client (a command,
     * a job): its key is the account
     */
    public static function keyOf(Request $request, User $user): string
    {
        $token = self::tokenOf($user);
        if ($token !== null) {
            $id = $token instanceof Model ? $token->getKey() : null;

            return 'token:'.(is_int($id) || is_string($id) ? $id : 'unknown');
        }

        return $request->hasSession()
            ? 'session:'.hash('sha256', $request->session()->getId())
            : 'account:'.$user->cuid;
    }

    /**
     * The API token of the account, if it signed in with one. The account of a session holds the TransientToken of
     * Sanctum, or no token at all (the session guard)
     */
    private static function tokenOf(User $user): ?HasAbilities
    {
        /** @var HasAbilities|null $token */
        $token = $user->currentAccessToken();

        return $token instanceof TransientToken ? null : $token;
    }
}
