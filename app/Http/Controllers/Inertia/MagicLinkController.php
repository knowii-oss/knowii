<?php

namespace App\Http\Controllers\Inertia;

use App\Actions\Auth\MagicLinks;
use App\Contracts\Auth\ConsumesMagicLinks;
use App\Contracts\Auth\SendsMagicLinks;
use App\Http\Controllers\Controller;
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
        app(SendsMagicLinks::class)->send($request->only(['email']));

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
        $user = app(ConsumesMagicLinks::class)->consume($token);
        if ($user === null) {
            return redirect()->route('login')->with('status', 'This sign-in link expired or was already used. Ask for a new one.');
        }

        // The link proves that the member reads this inbox
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // A link replaces the password, never the second factor: the challenge of Fortify comes next
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();
        Log::info('Signed in with a link', ['user' => $user->cuid]);

        return redirect()->intended(route('dashboard'));
    }
}
