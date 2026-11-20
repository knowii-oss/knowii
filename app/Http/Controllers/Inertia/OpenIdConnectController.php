<?php

namespace App\Http\Controllers\Inertia;

use App\Contracts\Auth\ConfirmsAccountDeletions;
use App\Contracts\Auth\ReadsOpenIdConnectIdentities;
use App\Contracts\Auth\SignsInWithOpenIdConnect;
use App\Enums\KnowiiAccountDeletionProof;
use App\Exceptions\OpenIdConnectException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountDeletion;
use App\Support\EntitlementReads;
use App\Support\Landing;
use App\Support\OpenIdConnect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sign in with an OpenID Connect provider, or connect it to the account of a signed-in member (#1385, ADR 0045), or
 * prove with a new sign-in that a member without a password wants to delete the account (#1521, ADR 0052). Without the
 * configuration, every route answers 404.
 *
 * The flow data (`state`, `nonce`, the PKCE verifier, its purpose, who started it and when) lives in the session and is
 * used once: the callback reads and deletes it first, so that no failure leaves a reusable flow behind. The three
 * purposes share the one callback address that the provider knows: the callback tells them apart by the purpose that the
 * server stored with the flow, never by a parameter of the browser. The intended address of the session (#1387: the page
 * that a visitor was reading) is kept, so that the sign-in brings the member back to it.
 */
class OpenIdConnectController extends Controller
{
    private const string SESSION_KEY = 'oidc.flow';

    /**
     * The purpose of a flow. A flow without a purpose (started before #1521) is a sign-in or a connection, as before.
     */
    private const string PURPOSE_SIGN_IN = 'sign_in';

    private const string PURPOSE_CONNECT = 'connect';

    private const string PURPOSE_DELETION = 'deletion';

    /**
     * The flash data of the result of a proof, for the deletion section of the profile page
     */
    public const string FLASH_DELETION_STATUS = 'account_deletion_status';

    public const string FLASH_DELETION_ERROR = 'account_deletion_error';

    public const string FLASH_DELETE_CONTENT = 'account_deletion_delete_content';

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

        return $this->start($request, $client, null, self::PURPOSE_SIGN_IN);
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
        return Inertia::location($this->start($request, $client, $member, self::PURPOSE_CONNECT));
    }

    /**
     * A signed-in member without a password starts the proof of a deletion (#1521, ADR 0052), from the deletion section
     * of the profile settings: a POST with the CSRF token of the member. The provider is asked for a new sign-in. Only an
     * account without a password and with a connected account of this provider can make it. The choice "also delete my
     * posts, comments and messages" goes with the flow and comes back with the result.
     */
    public function confirmDeletion(Request $request, ReadsOpenIdConnectIdentities $client): Response
    {
        abort_unless(OpenIdConnect::enabled(), 404);

        $member = $request->user();
        abort_unless($member instanceof User, 401);

        $deleteContent = $request->boolean('delete_content');
        $proof = AccountDeletion::proofOf($member);
        if ($proof !== KnowiiAccountDeletionProof::SignIn) {
            $e = $proof === KnowiiAccountDeletionProof::Password
                ? new OpenIdConnectException('has_password', 'This account has a password: confirm the deletion with your password.')
                : new OpenIdConnectException('no_identity', AccountDeletion::UNAVAILABLE_MESSAGE);

            return $this->fail($request, $member, $e, self::PURPOSE_DELETION, $deleteContent);
        }

        return Inertia::location($this->start($request, $client, $member, self::PURPOSE_DELETION, $deleteContent));
    }

    private static function passwordConfirmedRecently(Request $request): bool
    {
        $confirmedAt = $request->session()->get('auth.password_confirmed_at');
        $timeout = config()->integer('auth.password_timeout');

        return is_int($confirmedAt) && now()->getTimestamp() - $confirmedAt < $timeout;
    }

    /**
     * Open a flow: for a guest who signs in (no member), for the member who connects an account, or for the member who
     * proves the intent to delete the account. A new flow replaces the one that the session held.
     */
    private function start(Request $request, ReadsOpenIdConnectIdentities $client, ?User $member, string $purpose, bool $deleteContent = false): RedirectResponse
    {
        $state = Str::random(48);
        $nonce = Str::random(48);
        $verifier = self::base64Url(random_bytes(48));
        $challenge = self::base64Url(hash('sha256', $verifier, true));

        try {
            $url = $client->authorizationUrl($state, $nonce, $challenge, $purpose === self::PURPOSE_DELETION);
        } catch (OpenIdConnectException $e) {
            return $this->fail($request, $member, $e, $purpose, $deleteContent);
        }

        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'purpose' => $purpose,
            // The member who connects an account or makes the proof: the callback must come back to the same account
            'link_user_id' => $member?->id,
            'delete_content' => $deleteContent,
            'started_at' => now()->getTimestamp(),
        ]);

        return redirect()->away($url);
    }

    public function callback(Request $request, ReadsOpenIdConnectIdentities $client, SignsInWithOpenIdConnect $signsIn, ConfirmsAccountDeletions $confirmsDeletions): RedirectResponse
    {
        abort_unless(OpenIdConnect::enabled(), 404);

        $member = $request->user();
        // Read and deleted first: the flow is used once, whatever happens next
        $pulled = $request->session()->pull(self::SESSION_KEY);
        /** @var array<array-key, scalar|null>|null $stored The flow that start() stored: each value is narrowed where it is read */
        $stored = is_array($pulled) ? $pulled : null;
        $purpose = self::purposeOf($stored);
        $deleteContent = ($stored['delete_content'] ?? false) === true;

        if ($purpose === self::PURPOSE_DELETION) {
            return $this->confirmDeletionCallback($request, $member, $stored, $deleteContent, $client, $confirmsDeletions);
        }

        try {
            $flow = $this->checkRequest($request, $stored, $member);
            $identity = $client->identity($flow['code'], $flow['verifier'], $flow['nonce']);
            $user = $signsIn->resolve($identity, $member);
        } catch (OpenIdConnectException $e) {
            return $this->fail($request, $member, $e, $purpose);
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
            // This challenge replaces an earlier one of this session: the landing that a sign-in link left for it goes (#1612)
            Landing::forgetHeld($request);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();
        Log::info('Signed in with an OpenID Connect provider', ['user' => $user->cuid, 'issuer' => $identity->issuer]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * The end of the proof of a deletion (#1521, ADR 0052): the same checks of the request and of the ID token as a
     * sign-in, then the checks of the proof (ConfirmAccountDeletion). A GET: it records the proof and never deletes
     * anything. The member goes back to the deletion section, which says the result.
     *
     * @param  array<array-key, scalar|null>|null  $stored
     */
    private function confirmDeletionCallback(Request $request, ?User $member, ?array $stored, bool $deleteContent, ReadsOpenIdConnectIdentities $client, ConfirmsAccountDeletions $confirmsDeletions): RedirectResponse
    {
        try {
            $flow = $this->checkRequest($request, $stored, $member);
            // checkRequest() refuses a flow that comes back without the member who started it
            if (! $member instanceof User) {
                throw new OpenIdConnectException('session_changed', 'You signed in or out while this was going on. Please try again.');
            }
            $identity = $client->identity($flow['code'], $flow['verifier'], $flow['nonce']);
            $until = $confirmsDeletions->confirm($member, $identity, $flow['started_at']);
        } catch (OpenIdConnectException $e) {
            return $this->fail($request, $member, $e, self::PURPOSE_DELETION, $deleteContent);
        }

        return redirect()->to(AccountDeletion::pageUrl())
            ->with(self::FLASH_DELETION_STATUS, $until->toIso8601String())
            ->with(self::FLASH_DELETE_CONTENT, $deleteContent);
    }

    /**
     * The purpose that the server stored with the flow. A flow of an older version has none: a sign-in or a connection.
     *
     * @param  array<array-key, scalar|null>|null  $flow
     */
    private static function purposeOf(?array $flow): string
    {
        if ($flow === null) {
            return self::PURPOSE_SIGN_IN;
        }
        $purpose = $flow['purpose'] ?? null;
        if (in_array($purpose, [self::PURPOSE_SIGN_IN, self::PURPOSE_CONNECT, self::PURPOSE_DELETION], true)) {
            return $purpose;
        }

        return ($flow['link_user_id'] ?? null) === null ? self::PURPOSE_SIGN_IN : self::PURPOSE_CONNECT;
    }

    /**
     * Check the callback against the flow that was in the session. The caller read and deleted it first, so that it is
     * used once whatever happens next.
     *
     * @param  array<array-key, scalar|null>|null  $flow
     * @return array{code: string, verifier: string, nonce: string, started_at: int}
     *
     * @throws OpenIdConnectException
     */
    private function checkRequest(Request $request, ?array $flow, ?User $member): array
    {
        if ($flow === null) {
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

        return ['code' => $code, 'verifier' => $verifier, 'nonce' => $nonce, 'started_at' => $startedAt];
    }

    /**
     * A plain message on the sign-in page, or on the profile settings for a member: in the deletion section for a proof
     * (with the choice of the box). The log holds the reason, the issuer and the account, never a token, a code, a
     * subject or an address.
     */
    private function fail(Request $request, ?User $member, OpenIdConnectException $e, string $purpose, bool $deleteContent = false): RedirectResponse
    {
        $deletion = $purpose === self::PURPOSE_DELETION;
        Log::warning($deletion ? 'Account deletion proof refused' : 'OpenID Connect sign-in refused', [
            'reason' => $e->reason,
            'issuer' => OpenIdConnect::issuer(),
            'user' => $member?->cuid,
        ]);

        if (! $member instanceof User) {
            return redirect()->route('login')->with('oidc_error', $e->getMessage());
        }
        if ($deletion) {
            return redirect()->to(AccountDeletion::pageUrl())
                ->with(self::FLASH_DELETION_ERROR, $e->getMessage())
                ->with(self::FLASH_DELETE_CONTENT, $deleteContent);
        }

        return redirect()->route('profile.show')->with('oidc_error', $e->getMessage());
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
