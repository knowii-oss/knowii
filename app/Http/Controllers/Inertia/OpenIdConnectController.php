<?php

namespace App\Http\Controllers\Inertia;

use App\Contracts\Auth\ReadsOpenIdConnectIdentities;
use App\Contracts\Auth\SignsInWithOpenIdConnect;
use App\Exceptions\OpenIdConnectException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\EntitlementReads;
use App\Support\OpenIdConnect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sign in with an OpenID Connect provider, or connect it to the account of a signed-in member (#1385, ADR 0045).
 * Without the configuration, both routes answer 404.
 *
 * The flow data (`state`, `nonce`, the PKCE verifier, who started it and when) lives in the session and is used once:
 * the callback reads and deletes it first, so that no failure leaves a reusable flow behind. The intended address of the
 * session (#1387: the page that a visitor was reading) is kept, so that the sign-in brings the member back to it.
 */
class OpenIdConnectController extends Controller
{
    private const string SESSION_KEY = 'oidc.flow';

    private const int FLOW_MINUTES = 10;

    private const int MAX_CODE_LENGTH = 2048;

    /**
     * A guest signs in. A signed-in member never starts a flow here: a plain link can be opened by another site in the
     * browser of a member, and the provider account of an attacker would then be connected to the account of the member.
     * A member connects an account with connect().
     */
    public function redirect(Request $request, ReadsOpenIdConnectIdentities $client): RedirectResponse
    {
        abort_unless(OpenIdConnect::enabled(), 404);

        if ($request->user() instanceof User) {
            return redirect()->route('dashboard');
        }

        return $this->start($request, $client, null);
    }

    /**
     * A signed-in member connects a provider account, from the profile settings. A POST (so the request carries the
     * CSRF token of the member), and the password confirmed a moment ago when the account has one: the account gets
     * one more way in, so the member proves who they are first. An account without a password was created by the
     * provider and cannot confirm one.
     */
    public function connect(Request $request, ReadsOpenIdConnectIdentities $client): Response
    {
        abort_unless(OpenIdConnect::enabled(), 404);

        $member = $request->user();
        abort_unless($member instanceof User, 401);

        if ($member->password !== null && ! self::passwordConfirmedRecently($request)) {
            abort(423, 'Password confirmation required.');
        }

        // The address of the provider is outside the application: Inertia needs a full page visit
        return Inertia::location($this->start($request, $client, $member));
    }

    private static function passwordConfirmedRecently(Request $request): bool
    {
        $confirmedAt = $request->session()->get('auth.password_confirmed_at');
        $timeout = config()->integer('auth.password_timeout');

        return is_int($confirmedAt) && now()->getTimestamp() - $confirmedAt < $timeout;
    }

    /**
     * Open a flow: for a guest who signs in (no member), or for the member who connects an account
     */
    private function start(Request $request, ReadsOpenIdConnectIdentities $client, ?User $member): RedirectResponse
    {
        $state = Str::random(48);
        $nonce = Str::random(48);
        $verifier = self::base64Url(random_bytes(48));
        $challenge = self::base64Url(hash('sha256', $verifier, true));

        try {
            $url = $client->authorizationUrl($state, $nonce, $challenge);
        } catch (OpenIdConnectException $e) {
            return $this->fail($request, $member, $e);
        }

        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'link_user_id' => $member?->id,
            'started_at' => now()->getTimestamp(),
        ]);

        return redirect()->away($url);
    }

    public function callback(Request $request, ReadsOpenIdConnectIdentities $client, SignsInWithOpenIdConnect $signsIn): RedirectResponse
    {
        abort_unless(OpenIdConnect::enabled(), 404);

        $member = $request->user();

        try {
            $flow = $this->checkRequest($request, $member);
            $identity = $client->identity($flow['code'], $flow['verifier'], $flow['nonce']);
            $user = $signsIn->resolve($identity, $member);
        } catch (OpenIdConnectException $e) {
            return $this->fail($request, $member, $e);
        }

        // The link is made: the entitlements of the person are read at the provider, in the background (#1386, ADR 0047)
        EntitlementReads::afterSignIn($identity->issuer, $identity->subject);

        // The member connected the account from the profile settings
        if ($member instanceof User) {
            return redirect()->route('profile.show')->with('oidc_status', 'Connected.');
        }

        // The provider replaces the password, never the second factor: the challenge of Fortify comes next
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();
        Log::info('Signed in with an OpenID Connect provider', ['user' => $user->cuid, 'issuer' => $identity->issuer]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Check the callback against the flow in the session. The flow is read and deleted first, so that it is used once
     * whatever happens next.
     *
     * @return array{code: string, verifier: string, nonce: string}
     *
     * @throws OpenIdConnectException
     */
    private function checkRequest(Request $request, ?User $member): array
    {
        $flow = $request->session()->pull(self::SESSION_KEY);
        if (! is_array($flow)) {
            throw new OpenIdConnectException('no_flow', 'This sign-in expired or was not started here. Please try again.');
        }

        $state = $flow['state'] ?? null;
        $verifier = $flow['verifier'] ?? null;
        $nonce = $flow['nonce'] ?? null;
        $startedAt = $flow['started_at'] ?? null;
        $givenState = $request->query('state');
        if (! is_string($state) || ! is_string($verifier) || ! is_string($nonce) || ! is_int($startedAt) || ! is_string($givenState) || ! hash_equals($state, $givenState)) {
            throw new OpenIdConnectException('state_mismatch', 'This sign-in could not be checked. Please try again.');
        }
        if ($startedAt < now()->subMinutes(self::FLOW_MINUTES)->getTimestamp()) {
            throw new OpenIdConnectException('flow_expired', 'This sign-in expired. Please try again.');
        }

        // The member who started the flow to connect an account is the one who signed in. A flow that a guest started
        // does not go on in a session that is signed in now.
        $linkUserId = $flow['link_user_id'] ?? null;
        if ($linkUserId !== ($member?->id)) {
            throw new OpenIdConnectException('session_changed', 'You signed in or out while this was going on. Please try again.');
        }

        // The answer of the provider: an error (its text is never shown), or the issuer that it names (RFC 9207)
        $issuer = $request->query('iss');
        if ($request->query('error') !== null || ($issuer !== null && $issuer !== OpenIdConnect::issuer())) {
            throw new OpenIdConnectException('provider_refused');
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '' || strlen($code) > self::MAX_CODE_LENGTH) {
            throw new OpenIdConnectException('no_code');
        }

        return ['code' => $code, 'verifier' => $verifier, 'nonce' => $nonce];
    }

    /**
     * A plain message on the sign-in page, or on the profile settings for a member. The log holds the reason and the
     * issuer, never a token, a code, a subject or an address.
     */
    private function fail(Request $request, ?User $member, OpenIdConnectException $e): RedirectResponse
    {
        Log::warning('OpenID Connect sign-in refused', [
            'reason' => $e->reason,
            'issuer' => OpenIdConnect::issuer(),
            'user' => $member?->cuid,
        ]);

        return ($member instanceof User ? redirect()->route('profile.show') : redirect()->route('login'))
            ->with('oidc_error', $e->getMessage());
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
