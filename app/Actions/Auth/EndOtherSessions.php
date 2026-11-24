<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\EndsOtherSessions;
use App\Exceptions\AccountChangedException;
use App\Http\Middleware\EndOutdatedSessions;
use App\Models\User;
use App\Support\EndedSession;
use App\Support\LiveConnections;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends every other session of an account (ADR 0059, ADR 0066): 1 more in the version of the sessions, in one write with
 * the change that asks for it, and only when the account still has the version that the model read. See the contract.
 */
class EndOtherSessions implements EndsOtherSessions
{
    /**
     * What the sign-in page says to a session whose change was not written
     */
    public const string CHANGED_ELSEWHERE = 'Your account changed on another device. Sign in again.';

    final public function endFor(User $user, array $with = [], bool $newRememberToken = false, ?Closure $write = null): void
    {
        $version = $user->sessionVersion();
        $values = [
            ...$with,
            ...($newRememberToken ? ['remember_token' => Str::random(60)] : []),
            'session_version' => $version + 1,
        ];

        try {
            DB::transaction(static function () use ($user, $version, $values, $write): void {
                // The row as it is stored now, locked until the commit. It holds the version that the model read, or the
                // account changed elsewhere meanwhile: then nothing runs and nothing is written
                $stored = User::query()->whereKey($user->getKey())->lockForUpdate()->toBase()->value('session_version');
                if (! is_numeric($stored) || (int) $stored !== $version) {
                    throw new AccountChangedException;
                }

                if ($write !== null) {
                    $write();
                }

                // One write, which finds the row only with the version that the model read: its count says whether the
                // change is stored
                if (User::query()->whereKey($user->getKey())->where('session_version', $version)->update($values) !== 1) {
                    throw new AccountChangedException;
                }
            });
        } catch (AccountChangedException $exception) {
            // A session of this account that holds the version of before is a session that the other change ended
            throw $this->requestHoldsASessionOf($user) ? EndedSession::end(request(), self::CHANGED_ELSEWHERE) : $exception;
        }

        // The model holds what the row holds now, so its next write finds the row (User::setKeysForSaveQuery())
        $user->forceFill($values)->syncOriginalAttributes(array_keys($values));

        // The live connections of the account end with its sessions: the pages connect again with the sessions that are left
        // (ADR 0075)
        LiveConnections::endFor($user);

        $this->keepPresentSession($user, array_key_exists('password', $with));
    }

    /**
     * The session of the present request goes on when it is a session of this account: it holds the new version, as a
     * sign-in stores it (App\Listeners\StoreSessionVersion), and the password of now for each guard that keeps one in the
     * session (the checks of the password of the session, on the pages and on the API). After a new password it gets a new
     * id, as after a sign-in: the id of before signs nobody in
     */
    private function keepPresentSession(User $user, bool $newId): void
    {
        if (! $this->requestHoldsASessionOf($user)) {
            return;
        }

        $session = request()->session();
        if ($newId) {
            $session->migrate(true);
        }
        $session->put(EndOutdatedSessions::SESSION_KEY, $user->sessionVersion());

        // An account without a password keeps none in its session
        $password = (string) $user->password;
        if ($password === '') {
            return;
        }
        $guard = Auth::guard('web');
        foreach (['web', 'sanctum'] as $name) {
            if ($session->has('password_hash_'.$name)) {
                $session->put('password_hash_'.$name, $guard->hashPasswordForCookie($password));
            }
        }
    }

    private function requestHoldsASessionOf(User $user): bool
    {
        return request()->hasSession() && Auth::guard('web')->user()?->getAuthIdentifier() === $user->getAuthIdentifier();
    }
}
