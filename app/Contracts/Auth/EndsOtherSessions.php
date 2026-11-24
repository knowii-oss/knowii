<?php

namespace App\Contracts\Auth;

use App\Exceptions\AccountChangedException;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;

/**
 * The version of the sessions ends the other sessions of an account (ADR 0059, ADR 0066): a change that must sign the other
 * devices out adds 1 to the version of the account. A session holds the version of its sign-in, so every other session ends
 * at its next request, on every route and whatever the session driver (App\Http\Middleware\EndOutdatedSessions). The
 * callers: a change and a reset of the password, "Log out other browser sessions", and the second factor when it is
 * confirmed, turned off, or when its recovery codes are made again.
 */
interface EndsOtherSessions
{
    /**
     * Adds 1 to the version of the sessions of the account, in one write with the given values. The session of the present
     * request, when it is a session of this account, holds the new version and the password of now: it goes on. With a new
     * password it also gets a new session id, as after a sign-in. A request without a session of the account (a password
     * reset) keeps nothing.
     *
     * The change is written only when the account still has the version that the model read. In one transaction, the row
     * is locked and compared, the other write of the caller runs, and the write of the version counts its row. When the
     * account changed elsewhere after the model read it, nothing is written, the other write does not run, and the change
     * fails: the session of the request ends, because it is a session of before that other change, and the sign-in page
     * says so; a request without a session of the account gets AccountChangedException.
     *
     * @param  array<string, string|null>  $with  The other values of the same write: the new password of a change or of a reset
     * @param  bool  $newRememberToken  Write the remember token again too: a "Remember me" cookie of before signs nobody in
     * @param  (Closure(): void)|null  $write  Another write of the same change, in the same transaction, on the locked row: the write of Fortify for the second factor
     *
     * @throws AuthenticationException The account changed elsewhere meanwhile, and the session of the request is ended
     * @throws AccountChangedException The account changed elsewhere meanwhile, for a request without a session of the account
     */
    public function endFor(User $user, array $with = [], bool $newRememberToken = false, ?Closure $write = null): void;
}
