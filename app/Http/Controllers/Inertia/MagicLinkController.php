<?php

namespace App\Http\Controllers\Inertia;

use App\Actions\Auth\MagicLinks;
use App\Contracts\Auth\ConsumesMagicLinks;
use App\Contracts\Auth\SendsMagicLinks;
use App\Http\Controllers\Controller;
use App\Support\Landing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sign in with a link sent by email (#703, ADR 0023). The link opens a page with a button: mail scanners open links,
 * so a link that signed in on GET would be used up before the member clicks it.
 */
class MagicLinkController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // The link keeps the page of a community that this session holds: it lands there in another browser too (#1612)
        app(SendsMagicLinks::class)->send($request->only(['email']), Landing::fromSession());

        return redirect()->route('login')->with('status', sprintf(
            'If an account has this address, we sent it a sign-in link. The link works once, for %d minutes.',
            MagicLinks::VALIDITY_MINUTES,
        ));
    }

    public function show(string $token): Response
    {
        $user = app(ConsumesMagicLinks::class)->peek($token);

        return Inertia::render('Auth/MagicLink', [
            'token' => $token,
            'valid' => $user !== null,
            'email' => $user?->email,
        ]);
    }

    public function consume(Request $request, string $token): RedirectResponse
    {
        $links = app(ConsumesMagicLinks::class);
        // Read first: using the link up deletes its landing
        $landing = $links->pullLanding($token);
        $user = $links->consume($token);
        if ($user === null) {
            return redirect()->route('login')->with('status', 'This sign-in link expired or was already used. Ask for a new one.');
        }

        // The link proves that the member reads this inbox: its entry matched the address of the account (MagicLinks, ADR
        // 0058). The address check writes only while the account has the address that was read here and no verified address
        // (User::markEmailAsVerified(), ADR 0058): when it writes nothing, the account is read again. An account that
        // another proof verified a moment before signs in as usual; an account with another address now gets the answer of
        // a link that does not work
        if (! $user->hasVerifiedEmail()) {
            $address = $user->email;
            if (! $user->markEmailAsVerified()) {
                $user->refresh();
                if (! $user->hasVerifiedEmail() || mb_strtolower($user->email) !== mb_strtolower($address)) {
                    return redirect()->route('login')->with('status', 'This sign-in link expired or was already used. Ask for a new one.');
                }
            }
        }

        // A link replaces the password, never the second factor: the challenge of Fortify comes next. Nothing of the account
        // is decided before the code: the session keeps only the path of the landing of the link, and the answer of the
        // challenge reads it for the account that passed (App\Http\Responses\TwoFactorLoginResponse, #1612, ADR 0057)
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);
            Landing::holdForChallenge($request, $landing);

            return redirect()->route('two-factor.login');
        }

        // The landing of the link becomes the intended address when the session has none of its own, and only when the
        // account is a member of the community of the landing (#1612, ADR 0057)
        Landing::forgetHeld($request);
        Landing::rememberForMember($request, $user, $landing);

        Auth::login($user);
        $request->session()->regenerate();
        Log::info('Signed in with a link', ['user' => $user->cuid]);

        return redirect()->intended(route('dashboard'));
    }
}
