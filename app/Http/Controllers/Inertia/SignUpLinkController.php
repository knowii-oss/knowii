<?php

namespace App\Http\Controllers\Inertia;

use App\Actions\Auth\PendingSignUps;
use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Support\Landing;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;

/**
 * The link of the mail of a sign-up (ADR 0065). The link opens a page with a form: the person chooses the password there,
 * and the account is made with the button. Mail scanners open links, so the page does not use the link up. The account is
 * verified from its first write: the link proved the address. A session of an account that opens the link goes to the
 * dashboard with a line that says why, and the link stays valid.
 */
class SignUpLinkController extends Controller
{
    /**
     * The words of a link that does not work (an unknown token, a link that was used or expired)
     */
    public const string LINK_NOT_WORKING = 'This link expired or was already used. Sign up again.';

    /**
     * The words on the sign-in page when the address got an account between the mail and the use of the link
     */
    public const string ADDRESS_HAS_ACCOUNT = 'This email address already has an account. Sign in, or reset your password.';

    /**
     * The words on the dashboard when the link is opened in a session of an account: the link stays valid
     */
    public const string SIGNED_IN_WITH_ANOTHER_ACCOUNT = 'You are signed in with another account. Sign out, then open the link again.';

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return self::signedIn();
        }

        $entry = PendingSignUps::peek($token);

        return Inertia::render('Auth/FinishSignUp', [
            'token' => $token,
            'valid' => $entry !== null,
            'email' => $entry['email'] ?? null,
            'name' => $entry['name'] ?? null,
        ]);
    }

    /**
     * Makes the account, in this order: the fields of the page (an error keeps the link), the use of the link, the account,
     * then the sign-in and the landing of the address check
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        if ($request->user() !== null) {
            return self::signedIn();
        }

        $creator = app(CreateNewUser::class);
        $page = redirect()->route('register.link.show', ['token' => $token]);

        // An error keeps the link: the entry is only read until the fields pass
        if (PendingSignUps::peek($token) === null) {
            return $page;
        }
        try {
            $creator->validateFinish($request->all());
        } catch (ValidationException $e) {
            return $page->withErrors($e->errors())->withInput($request->only('name', 'terms'));
        }

        // Read and removed at once: the link works once
        $entry = PendingSignUps::pull($token);
        if ($entry === null) {
            return $page;
        }

        try {
            $user = $creator->createFromLink($request->only('name', 'password', 'password_confirmation', 'terms', 'time_zone'), $entry['email']);
        } catch (UniqueConstraintViolationException $e) {
            if (CreateNewUser::isAddressTaken($e, $entry['email'])) {
                // Another sign-up, a provider sign-in or a second link was first: no second account
                return redirect()->route('login')->with('status', self::ADDRESS_HAS_ACCOUNT);
            }

            Log::warning('Sign-up refused: a unique value was taken at the write', ['index' => $e->index]);

            return $page->withErrors(['email' => CreateNewUser::NOT_CREATED_MESSAGE]);
        }

        // The listener of the framework sends no address check to a verified account
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        Log::info('Sign-up finished with its link', ['user' => $user->cuid]);

        // The landing of the sign-up form, as the answer of the address check: an invitation or an invite link that the
        // session holds is kept (#1612, ADR 0057)
        Landing::remember($request, $user, $entry['landing']);

        return redirect()->intended(Fortify::redirects('email-verification').'?verified=1');
    }

    /**
     * A session of an account opened the link: the link is not used, and the dashboard says why
     */
    private static function signedIn(): RedirectResponse
    {
        return redirect()->route('dashboard')->with('flash', ['banner' => self::SIGNED_IN_WITH_ANOTHER_ACCOUNT, 'bannerStyle' => 'danger']);
    }
}
