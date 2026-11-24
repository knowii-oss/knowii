<?php

namespace App\Http\Controllers\Inertia;

use App\Actions\Auth\MagicLinks;
use App\Contracts\Auth\ConsumesMagicLinks;
use App\Contracts\Auth\SendsMagicLinks;
use App\Contracts\Auth\TakesOverUnverifiedAccounts;
use App\Enums\KnowiiAddressProof;
use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactorLoginRequest;
use App\Models\User;
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
    public const string HELP_LINE = 'No email after a few minutes? Look in your spam folder, then ask again. An address that refused our mail before can need a second try.';

    public function store(Request $request): RedirectResponse
    {
        // The link keeps the page of a community that this session holds: it lands there in another browser too (#1612)
        app(SendsMagicLinks::class)->send($request->only(['email']), Landing::fromSession());

        // One more line, the same for every address (#1478): an address that the mail provider listed after a bounce or a
        // complaint can need a second try, and the answer never tells whether it is the case
        return redirect()->route('login')->with('status', sprintf(
            "If an account has this address, we sent it a sign-in link. The link works once, for %d minutes.\n%s",
            MagicLinks::VALIDITY_MINUTES,
            self::HELP_LINE,
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

        // The link proves that the person reads this inbox. For an account whose address was never verified it is the first
        // proof of the address, and the first proof takes the account over (ADR 0059): the address is verified, and the
        // password, the second factor, the sessions and the tokens of before go. No challenge follows then. The session of
        // this request stays: it becomes a session of the account only below, after the takeover. When the takeover finds
        // the address verified (another proof came first), the link goes on as for a verified account. When it finds
        // another address (the address was corrected after the link was checked), or no account (it was deleted after the
        // link was checked), the link proves nothing about the account: the answer of a link that does not work. A verified
        // account keeps its verified address through a change of the address (ADR 0058): nothing of it changes here
        if ($user->addressWasNeverVerified()) {
            // The address of the link: the entry of the link matched it (MagicLinks, ADR 0058)
            $provedAddress = $user->email;
            if (! app(TakesOverUnverifiedAccounts::class)->takeOver($user, KnowiiAddressProof::SignInLink, $provedAddress)->provesTheAccount()) {
                return redirect()->route('login')->with('status', 'This sign-in link expired or was already used. Ask for a new one.');
            }
        }

        // The account as it is stored now, read again by its key: an account that was deleted after the link was checked
        // signs nobody in, and the answer is the one of a link that does not work
        $user = User::query()->find($user->id);
        if ($user === null) {
            return redirect()->route('login')->with('status', 'This sign-in link expired or was already used. Ask for a new one.');
        }

        // A link replaces the password, never the second factor: the challenge of Fortify comes next. Nothing of the account
        // is decided before the code: the session keeps only the path of the landing of the link, and the answer of the
        // challenge reads it for the account that passed (App\Http\Responses\TwoFactorLoginResponse, #1612, ADR 0057)
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false, TwoFactorLoginRequest::STARTED_AT_KEY => TwoFactorLoginRequest::startedNow()]);
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
