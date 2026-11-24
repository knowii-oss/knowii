<?php

namespace App\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * The end of the session of a request that its account did not ask for: a session of an earlier version of its account
 * (App\Http\Middleware\EndOutdatedSessions), a change of an account that changed elsewhere while the request ran
 * (App\Contracts\Auth\EndsOtherSessions), and too many wrong passwords (App\Support\PasswordTries).
 *
 * The account is signed out in this browser, its "Remember me" cookie is forgotten there, and the session is invalidated:
 * the browser gets a new, empty session. The answer is the one of an expired session: a redirect to the sign-in page (also
 * for the form of a page), or 401 for a request that expects JSON. The sign-in page tells the browser to forget the pages
 * of the member that its history keeps (App\Support\BrowserHistory).
 */
final class EndedSession
{
    /**
     * The key of the session that holds the words for the sign-in page, for one request
     */
    public const string WARNING_KEY = 'warning';

    /**
     * Ends the session of the request, and gives the answer for the caller to throw. With words, the sign-in page shows
     * them as a warning (its `warning`, never its `status`, which is the word of a success) and holds them as an error
     * (`session`, in the error bag of the form that the browser sent), and the JSON answer holds them as its message
     */
    public static function end(Request $request, ?string $words = null): AuthenticationException
    {
        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        BrowserHistory::clear($request);

        if ($words !== null) {
            $request->session()->flash(self::WARNING_KEY, $words);
            // The words are an error of the sign-in page too: the form that the browser sent sees a refusal, never a success
            $request->session()->flash('errors', (new ViewErrorBag)->put('default', new MessageBag(['session' => [$words]])));
        }

        return new AuthenticationException($words ?? 'Unauthenticated.', ['web']);
    }
}
