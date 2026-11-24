<?php

namespace App\Http\Controllers;

use App\Contracts\Auth\EndsOtherSessions;
use App\Models\User;
use App\Support\BrowserHistory;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmPassword;
use Laravel\Jetstream\Http\Controllers\Inertia\OtherBrowserSessionsController as JetstreamOtherBrowserSessionsController;

/**
 * "Log out other browser sessions" of Jetstream. It ends every other session of the account, on every route and whatever
 * the session driver (ADR 0066): the version of the sessions goes up by 1 (App\Contracts\Auth\EndsOtherSessions), in one
 * transaction with the steps of Jetstream (a new hash of the same password, so that a "Remember me" cookie of another
 * browser signs nobody in, and the removal of the rows of the other sessions of the `database` driver). The session of the
 * request goes on, on the pages, on the routes of Fortify and on the API, and so does its own "Remember me" cookie.
 *
 * After it, the next page tells the browser to forget the pages that its history keeps (App\Support\BrowserHistory), as
 * after a sign-out. The password is checked first, with the tries of the session (App\Support\PasswordTries), outside the
 * transaction: a wrong password changes nothing.
 */
class OtherBrowserSessionsController extends JetstreamOtherBrowserSessionsController
{
    /**
     * @return RedirectResponse
     *
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public function destroy(Request $request, StatefulGuard $guard)
    {
        $user = $request->user();
        $password = $request->input('password');
        if (! $user instanceof User || ! is_string($password) || ! app(ConfirmPassword::class)($guard, $user, $password)) {
            throw ValidationException::withMessages([
                'password' => __('The password is incorrect.'),
            ]);
        }

        app(EndsOtherSessions::class)->endFor($user, write: function () use ($request, $password): void {
            Auth::guard('web')->logoutOtherDevices($password);
            $this->deleteOtherSessionRecords($request);
        });

        BrowserHistory::clear($request);

        return back(303);
    }
}
