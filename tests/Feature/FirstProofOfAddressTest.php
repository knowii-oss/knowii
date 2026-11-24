<?php

use App\Actions\Auth\MagicLinks;
use App\ApiRoutes;
use App\Contracts\Auth\ConsumesMagicLinks;
use App\Contracts\Auth\ReadsOpenIdConnectIdentities;
use App\Contracts\Auth\TakesOverUnverifiedAccounts;
use App\Contracts\Realtime\EndsLiveConnections;
use App\Contracts\Users\DeletesAccounts;
use App\Contracts\Users\UpdatesTimeZones;
use App\Enums\KnowiiAddressProof;
use App\Enums\KnowiiTakeOverResult;
use App\Http\Responses\FailedPasswordResetResponse;
use App\Mail\MagicLink;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UserIdentity;
use App\Notifications\ResetPasswordLink;
use App\Support\OpenIdIdentity;
use App\Support\ReverbLiveConnections;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmPassword;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

/**
 * The first proof of an address takes the account over (ADR 0059): a sign-in link and a password reset, as the sign-in
 * with a provider (tests/Feature/OpenIdConnectTest.php, case 4). Only an account whose address was never verified is taken
 * over: a verified account keeps everything, also while a change of its address waits for its confirmation (ADR 0058).
 * The sessions of every driver: tests/Feature/SessionVersionTest.php.
 */
const FIRST_PROOF_EMAIL = 'ada@example.com';

const FIRST_PROOF_PASSWORD = 'the-password-of-before-2026';

const FIRST_PROOF_NEW_PASSWORD = 'A-new-password-of-2026!';

const FIRST_PROOF_SESSION = 'the-session-of-before';

const FIRST_PROOF_REMEMBER_TOKEN = 'the-remember-token-of-before';

const FIRST_PROOF_NOW = '2026-11-20 10:00:00';

const FIRST_PROOF_VERIFIED_AT = '2026-11-01 08:00:00';

// The new address that a verified member asks for in the profile: it waits for its confirmation (ADR 0058)
const FIRST_PROOF_NEW_ADDRESS = 'ada.new@example.org';

const FIRST_PROOF_ISSUER = 'https://auth.example.test';

const FIRST_PROOF_OTHER_ISSUER = 'https://other.example.test';

// Where the session keeps the landing of a sign-in link during the challenge of the second factor
const FIRST_PROOF_HELD_LANDING = 'login.landing';

const FIRST_PROOF_MIGRATION = '2026_11_21_100000_add_session_version_to_users.php';

/**
 * An account with everything that signs in or acts for it: a password, a remember token, the second factor, a session
 * row, an API token and a password reset token.
 *
 * @return array{user: User, secret: string, apiToken: string}
 */
function firstProofAccount(bool $verified): array
{
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create([
        'name' => 'Ada Lovelace',
        'username' => 'ada-lovelace',
        'email' => FIRST_PROOF_EMAIL,
        'email_verified_at' => $verified ? Carbon::parse(FIRST_PROOF_VERIFIED_AT) : null,
        'password' => Hash::make(FIRST_PROOF_PASSWORD),
    ]);
    $user->forceFill([
        'remember_token' => FIRST_PROOF_REMEMBER_TOKEN,
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one'])),
        'two_factor_confirmed_at' => Carbon::parse(FIRST_PROOF_VERIFIED_AT),
    ])->save();
    expect($user->fresh()?->addressWasNeverVerified())->toBe(! $verified);

    $apiToken = $user->createToken('the token of before', ['*'])->plainTextToken;
    DB::table('sessions')->insert(['id' => FIRST_PROOF_SESSION, 'user_id' => $user->id, 'ip_address' => '203.0.113.7', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => now()->getTimestamp()]);
    // Ten minutes old: still good, and a new reset mail can be asked
    DB::table('password_reset_tokens')->insert(['email' => FIRST_PROOF_EMAIL, 'token' => Hash::make('the-reset-token-of-before'), 'created_at' => now()->subMinutes(10)]);

    return ['user' => $user, 'secret' => $secret, 'apiToken' => $apiToken];
}

/**
 * Asks for a sign-in link, and returns the token of the mail
 */
function firstProofSignInLink(string $email = FIRST_PROOF_EMAIL): string
{
    Mail::fake();
    // The test asks again at once: the link of a minute ago does not hold the next one back
    $cuid = User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->value('cuid');
    Cache::forget('magic-link-sent:'.$cuid);
    test()->post('/login/link', ['email' => $email])->assertRedirect(route('login'));
    $token = '';
    Mail::assertQueued(MagicLink::class, function (MagicLink $mail) use (&$token) {
        $token = $mail->token;

        return true;
    });

    return $token;
}

/**
 * Asks for a password reset for the address that the account has now, and returns the token of the mail
 */
function firstProofResetToken(User $user): string
{
    $user = $user->fresh() ?? $user;
    Notification::fake();
    test()->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
    $token = '';
    Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    return $token;
}

/**
 * Another browser, or another device: no cookie, and nobody is signed in. The sessions that the store holds stay: only the
 * memory of the test forgets this one
 */
function firstProofNewBrowser(): void
{
    session()->flush();
    // A request of the API makes the guard of Sanctum the default one: a new request starts with the default of the
    // application
    app('auth')->shouldUse('web');
    app('auth')->forgetGuards();
    test()->flushHeaders();
    // The cookies of the test client stay from one request to the next: this browser holds none
    $case = test()->target;
    Closure::bind(function (): void {
        $this->defaultCookies = [];
    }, $case, $case::class)();
}

/**
 * The session driver of the test, for every request that comes next. The handler proves it
 */
function firstProofSessionDriver(string $driver): void
{
    config(['session.driver' => $driver]);
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    app('auth')->forgetGuards();

    expect(app('session')->driver()->getHandler())->toBeInstanceOf($driver === 'database' ? DatabaseSessionHandler::class : ArraySessionHandler::class);
}

/**
 * The status of the answer of the API to the token, from a client without a session
 */
function firstProofApiStatus(string $apiToken): int
{
    firstProofNewBrowser();

    return test()->getJson('/api/v1/user', ['Authorization' => 'Bearer '.$apiToken])->getStatusCode();
}

/**
 * The sign-in page refuses the password, from a new browser: the answer is the error of wrong credentials, not the
 * challenge of the second factor
 */
function firstProofPasswordIsRefused(string $password): void
{
    firstProofNewBrowser();
    test()->post('/login', ['email' => FIRST_PROOF_EMAIL, 'password' => $password])->assertSessionHasErrors('email')->assertSessionMissing('login.id');
    test()->assertGuest();
}

/**
 * The provider of the instance, faked: it vouches for this address, for the account `$subject` at the provider
 */
function firstProofFakeProvider(string $email, string $subject = 'sub-of-the-owner'): void
{
    config(['services.oidc' => ['issuer' => FIRST_PROOF_ISSUER, 'client_id' => 'knowii-test-client', 'client_secret' => 'a-secret-that-is-not-real', 'scopes' => 'openid profile email', 'label' => 'Example account']]);
    app()->instance(ReadsOpenIdConnectIdentities::class, new class($email, $subject) implements ReadsOpenIdConnectIdentities
    {
        public function __construct(private readonly string $email, private readonly string $subject) {}

        public function authorizationUrl(string $state, string $nonce, string $codeChallenge, bool $freshSignIn = false): string
        {
            return FIRST_PROOF_ISSUER.'/authorize?state='.$state;
        }

        public function identity(string $code, string $codeVerifier, string $nonce): OpenIdIdentity
        {
            return new OpenIdIdentity(FIRST_PROOF_ISSUER, $this->subject, $this->email, true, 'Ada Lovelace');
        }
    });
}

/**
 * The browser goes to the provider and comes back: the answer of the callback
 */
function firstProofSignInWithTheProvider(): TestResponse
{
    $start = test()->get('/auth/oidc/redirect');
    $start->assertRedirect();
    parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $query);

    return test()->get('/auth/oidc/callback?code=c&state='.($query['state'] ?? ''));
}

/**
 * A member with a verified address who asked for another address in the profile: the new address waits for its
 * confirmation (ADR 0058), and the account keeps its verified address. The member also connected an account of another
 * provider
 *
 * @return array{user: User, secret: string, apiToken: string}
 */
function firstProofMemberWithANewAddress(): array
{
    $account = firstProofAccount(verified: true);
    $user = $account['user'];
    UserIdentity::factory()->create(['user_id' => $user->id, 'issuer' => FIRST_PROOF_OTHER_ISSUER, 'subject' => 'sub-of-the-member']);

    Mail::fake();
    test()->actingAs($user)->put('/user/profile-information', ['name' => 'Ada Lovelace', 'email' => FIRST_PROOF_NEW_ADDRESS, 'current_password' => FIRST_PROOF_PASSWORD])->assertSessionHasNoErrors();
    $fresh = $user->fresh();
    expect($fresh->email)->toBe(FIRST_PROOF_EMAIL)
        ->and($fresh->pending_email)->toBe(FIRST_PROOF_NEW_ADDRESS)
        ->and($fresh->email_verified_at?->toDateTimeString())->toBe(FIRST_PROOF_VERIFIED_AT)
        ->and($fresh->addressWasNeverVerified())->toBeFalse();
    firstProofNewBrowser();

    return $account;
}

/**
 * The member kept everything: the verified address, the pending address, the password, the second factor, the API token,
 * the connection to the other provider, the session row and the version of the sessions
 *
 * @param  array{user: User, secret: string, apiToken: string}  $account
 */
function firstProofMemberKeptEverything(array $account): void
{
    $user = $account['user'];
    $fresh = $user->fresh();
    expect(Hash::check(FIRST_PROOF_PASSWORD, (string) $fresh->password))->toBeTrue()
        ->and($fresh->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($fresh->two_factor_recovery_codes)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(1)
        ->and($user->identities()->where('issuer', FIRST_PROOF_OTHER_ISSUER)->count())->toBe(1)
        ->and(DB::table('sessions')->where('id', FIRST_PROOF_SESSION)->count())->toBe(1)
        ->and($fresh->session_version)->toBe(0)
        ->and($fresh->email)->toBe(FIRST_PROOF_EMAIL)
        ->and($fresh->email_verified_at?->toDateTimeString())->toBe(FIRST_PROOF_VERIFIED_AT)
        ->and($fresh->pending_email)->toBe(FIRST_PROOF_NEW_ADDRESS)
        ->and(firstProofApiStatus($account['apiToken']))->toBe(200);
}

beforeEach(function () {
    $this->travelTo(Carbon::parse(FIRST_PROOF_NOW));
});

// ---------------------------------------------------------------------------------------------------------------------
// The sign-in link
// ---------------------------------------------------------------------------------------------------------------------

test('a sign-in link is the first proof of an address that was never verified: the account goes to the person who opens it', function () {
    $account = firstProofAccount(verified: false);
    $user = $account['user'];
    $token = firstProofSignInLink();

    // The second factor of before is gone with the rest: no challenge
    $this->post('/login/link/'.$token)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $fresh = $user->fresh();
    expect($fresh->email_verified_at?->toDateTimeString())->toBe(FIRST_PROOF_NOW)
        ->and($fresh->session_version)->toBe(1)
        ->and($fresh->password)->toBeNull()
        ->and($fresh->hasPassword())->toBeFalse()
        ->and($fresh->remember_token)->not->toBe(FIRST_PROOF_REMEMBER_TOKEN)
        ->and($fresh->remember_token)->not->toBeNull()
        ->and($fresh->two_factor_secret)->toBeNull()
        ->and($fresh->two_factor_recovery_codes)->toBeNull()
        ->and($fresh->two_factor_confirmed_at)->toBeNull()
        ->and(DB::table('sessions')->where('id', FIRST_PROOF_SESSION)->count())->toBe(0)
        ->and($user->tokens()->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', FIRST_PROOF_EMAIL)->count())->toBe(0)
        // What the first person wrote stays
        ->and($fresh->name)->toBe('Ada Lovelace')
        ->and($fresh->username)->toBe('ada-lovelace')
        ->and($fresh->email)->toBe(FIRST_PROOF_EMAIL)
        ->and(User::query()->count())->toBe(1);

    expect(firstProofApiStatus($account['apiToken']))->toBe(401);
    firstProofPasswordIsRefused(FIRST_PROOF_PASSWORD);
    $this->postJson('/api/v1/auth/login', ['email' => FIRST_PROOF_EMAIL, 'password' => FIRST_PROOF_PASSWORD])->assertUnauthorized();
});

test('the session of the person who uses the sign-in link stays, and the sessions of before are deleted', function () {
    firstProofSessionDriver('database');
    $user = firstProofAccount(verified: false)['user'];
    $token = firstProofSignInLink();

    $this->get('/login/link/'.$token)->assertOk();
    $response = $this->post('/login/link/'.$token)->assertRedirect(route('dashboard'));

    $sessions = DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all();
    expect($sessions)->toBe([session()->getId()])
        ->and($sessions)->not->toContain(FIRST_PROOF_SESSION);

    // Without the cookie of the session, nobody is signed in
    $name = config()->string('session.cookie');
    $cookie = [$name => (string) $response->getCookie($name)?->getValue()];
    firstProofNewBrowser();
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();

    // With the cookie only, the browser is signed in as the account
    firstProofNewBrowser();
    $this->withCookies($cookie)->get('/dashboard')->assertOk();
    $this->assertAuthenticatedAs($user);
});

test('a sign-in link changes nothing of an account with a verified address', function () {
    $account = firstProofAccount(verified: true);
    $user = $account['user'];
    $before = $user->fresh();
    $token = firstProofSignInLink();

    // The second factor stays: the challenge comes, and the code signs in
    $this->post('/login/link/'.$token)->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($account['secret'])])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $fresh = $user->fresh();
    expect($fresh->email_verified_at?->toDateTimeString())->toBe(FIRST_PROOF_VERIFIED_AT)
        ->and($fresh->session_version)->toBe(0)
        ->and($fresh->password)->toBe($before->password)
        ->and(Hash::check(FIRST_PROOF_PASSWORD, (string) $fresh->password))->toBeTrue()
        ->and($fresh->remember_token)->toBe(FIRST_PROOF_REMEMBER_TOKEN)
        ->and($fresh->two_factor_secret)->toBe($before->two_factor_secret)
        ->and($fresh->two_factor_recovery_codes)->toBe($before->two_factor_recovery_codes)
        ->and($fresh->two_factor_confirmed_at)->toBe($before->two_factor_confirmed_at)
        ->and($fresh->two_factor_confirmed_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('id', FIRST_PROOF_SESSION)->count())->toBe(1)
        ->and($user->tokens()->count())->toBe(1)
        ->and(DB::table('password_reset_tokens')->where('email', FIRST_PROOF_EMAIL)->count())->toBe(1)
        ->and(firstProofApiStatus($account['apiToken']))->toBe(200);
});

test('a sign-in link for the pending address of a member signs nobody in; a link to the address of the account gets the challenge, and the member keeps everything', function () {
    $account = firstProofMemberWithANewAddress();
    $user = $account['user'];

    // The pending address is not the address of the account: no link goes to it
    Mail::fake();
    $this->post('/login/link', ['email' => FIRST_PROOF_NEW_ADDRESS])->assertRedirect(route('login'));
    Mail::assertNothingQueued();

    // A link to the address of the account: the second factor stays, and the challenge comes
    $this->post('/login/link/'.firstProofSignInLink())->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($account['secret'])])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    firstProofMemberKeptEverything($account);
});

test('a sign-in link works for the address that it was sent to: it no longer works when the account has another address', function () {
    $user = firstProofAccount(verified: true)['user'];
    $token = firstProofSignInLink();
    expect(app(ConsumesMagicLinks::class)->peek($token)?->is($user))->toBeTrue();

    // The change of the address was confirmed meanwhile (ADR 0058): the account has the new address, verified
    $user->forceFill(['email' => 'grace@example.com', 'email_verified_at' => Carbon::parse(FIRST_PROOF_NOW)])->save();
    $before = $user->fresh();

    $this->get('/login/link/'.$token)->assertOk()->assertInertia(fn ($page) => $page->where('valid', false)->where('email', null));
    $this->post('/login/link/'.$token)->assertRedirect(route('login'))->assertSessionHas('status', 'This sign-in link expired or was already used. Ask for a new one.');
    $this->assertGuest();

    $fresh = $user->fresh();
    expect($fresh->hasVerifiedEmail())->toBeTrue()
        ->and($fresh->password)->toBe($before->password)
        ->and($fresh->two_factor_secret)->toBe($before->two_factor_secret)
        ->and($user->tokens()->count())->toBe(1)
        ->and(DB::table('sessions')->where('id', FIRST_PROOF_SESSION)->count())->toBe(1);

    // A link for the new address works, and the challenge comes
    $this->post('/login/link/'.firstProofSignInLink('grace@example.com'))->assertRedirect(route('two-factor.login'));
    expect($user->fresh()?->password)->toBe($before->password);
});

test('an entry of the cache that holds an account without the hash of an address is no sign-in link', function () {
    $user = firstProofAccount(verified: false)['user'];
    $before = $user->fresh();
    $token = str_repeat('k', 64);
    Cache::put('magic-link:'.hash('sha256', $token), $user->id, now()->addMinutes(MagicLinks::VALIDITY_MINUTES));

    expect(app(ConsumesMagicLinks::class)->peek($token))->toBeNull();
    $this->post('/login/link/'.$token)->assertRedirect(route('login'));
    $this->assertGuest();

    $fresh = $user->fresh();
    expect($fresh->hasVerifiedEmail())->toBeFalse()
        ->and($fresh->password)->toBe($before->password)
        ->and($user->tokens()->count())->toBe(1);
});

// ---------------------------------------------------------------------------------------------------------------------
// The password reset
// ---------------------------------------------------------------------------------------------------------------------

test('a password reset is the first proof of an address that was never verified: the account goes to the person who resets it, with the new password', function () {
    $account = firstProofAccount(verified: false);
    $user = $account['user'];
    $token = firstProofResetToken($user);

    $this->post('/reset-password', ['token' => $token, 'email' => FIRST_PROOF_EMAIL, 'password' => FIRST_PROOF_NEW_PASSWORD, 'password_confirmation' => FIRST_PROOF_NEW_PASSWORD])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));
    $this->assertGuest();

    $fresh = $user->fresh();
    expect($fresh->email_verified_at?->toDateTimeString())->toBe(FIRST_PROOF_NOW)
        ->and($fresh->session_version)->toBe(1)
        ->and(Hash::check(FIRST_PROOF_NEW_PASSWORD, (string) $fresh->password))->toBeTrue()
        ->and(Hash::check(FIRST_PROOF_PASSWORD, (string) $fresh->password))->toBeFalse()
        ->and($fresh->remember_token)->not->toBe(FIRST_PROOF_REMEMBER_TOKEN)
        ->and($fresh->two_factor_secret)->toBeNull()
        ->and($fresh->two_factor_recovery_codes)->toBeNull()
        ->and($fresh->two_factor_confirmed_at)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->tokens()->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', FIRST_PROOF_EMAIL)->count())->toBe(0)
        ->and($fresh->name)->toBe('Ada Lovelace')
        ->and($fresh->username)->toBe('ada-lovelace');

    expect(firstProofApiStatus($account['apiToken']))->toBe(401);
    firstProofPasswordIsRefused(FIRST_PROOF_PASSWORD);

    // The new password signs in, without a challenge of the second factor
    firstProofNewBrowser();
    $this->post('/login', ['email' => FIRST_PROOF_EMAIL, 'password' => FIRST_PROOF_NEW_PASSWORD])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('a password reset that fails changes nothing of an account whose address was never verified', function (array $input) {
    $account = firstProofAccount(verified: false);
    $user = $account['user'];
    $before = $user->fresh();
    $token = firstProofResetToken($user);

    $this->post('/reset-password', ['token' => $token, 'email' => FIRST_PROOF_EMAIL, 'password' => FIRST_PROOF_NEW_PASSWORD, 'password_confirmation' => FIRST_PROOF_NEW_PASSWORD, ...$input])
        ->assertSessionHasErrors();

    $fresh = $user->fresh();
    expect($fresh->hasVerifiedEmail())->toBeFalse()
        ->and($fresh->session_version)->toBe(0)
        ->and($fresh->password)->toBe($before->password)
        ->and($fresh->remember_token)->toBe(FIRST_PROOF_REMEMBER_TOKEN)
        ->and($fresh->two_factor_secret)->toBe($before->two_factor_secret)
        ->and(DB::table('sessions')->where('id', FIRST_PROOF_SESSION)->count())->toBe(1)
        ->and($user->tokens()->count())->toBe(1);
})->with([
    'a wrong token' => [['token' => 'not-the-token-of-the-mail']],
    'a new password that the rules refuse' => [['password' => 'short', 'password_confirmation' => 'short']],
    'a confirmation that differs' => [['password_confirmation' => 'Another-password-of-2026!']],
]);

test('a password reset changes the password of an account with a verified address, and ends its sessions by their version', function () {
    $account = firstProofAccount(verified: true);
    $user = $account['user'];
    $before = $user->fresh();
    $token = firstProofResetToken($user);

    $this->post('/reset-password', ['token' => $token, 'email' => FIRST_PROOF_EMAIL, 'password' => FIRST_PROOF_NEW_PASSWORD, 'password_confirmation' => FIRST_PROOF_NEW_PASSWORD])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    $fresh = $user->fresh();
    expect($fresh->email_verified_at?->toDateTimeString())->toBe(FIRST_PROOF_VERIFIED_AT)
        // A reset ends every session of the account: the version goes up by 1 (ADR 0066)
        ->and($fresh->session_version)->toBe(1)
        ->and(Hash::check(FIRST_PROOF_NEW_PASSWORD, (string) $fresh->password))->toBeTrue()
        ->and($fresh->two_factor_secret)->toBe($before->two_factor_secret)
        ->and($fresh->two_factor_recovery_codes)->toBe($before->two_factor_recovery_codes)
        ->and($fresh->two_factor_confirmed_at)->toBe($before->two_factor_confirmed_at)
        ->and($fresh->two_factor_confirmed_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('id', FIRST_PROOF_SESSION)->count())->toBe(1)
        ->and($user->tokens()->count())->toBe(1)
        ->and(firstProofApiStatus($account['apiToken']))->toBe(200);

    // The second factor stays: the new password gets the challenge
    firstProofNewBrowser();
    $this->post('/login', ['email' => FIRST_PROOF_EMAIL, 'password' => FIRST_PROOF_NEW_PASSWORD])->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('a password reset of the pending address of a member gets the same answer as every address, sends no mail, and the member keeps everything', function () {
    $account = firstProofMemberWithANewAddress();

    Notification::fake();
    $this->from('/forgot-password')->post('/forgot-password', ['email' => FIRST_PROOF_NEW_ADDRESS])
        ->assertRedirect('/forgot-password')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'If an account has this address, we sent it a password reset link. The link works for 60 minutes.');
    Notification::assertNothingSent();

    firstProofMemberKeptEverything($account);
});

// ---------------------------------------------------------------------------------------------------------------------
// The provider
// ---------------------------------------------------------------------------------------------------------------------

test('the provider with the pending address of a member makes a new account: the member keeps everything, and the change then finds the address taken', function () {
    $account = firstProofMemberWithANewAddress();
    $user = $account['user'];
    firstProofFakeProvider(FIRST_PROOF_NEW_ADDRESS, 'sub-of-the-new-address');

    firstProofSignInWithTheProvider()->assertRedirect(route('dashboard'));

    $created = User::query()->where('email', FIRST_PROOF_NEW_ADDRESS)->firstOrFail();
    expect($created->is($user))->toBeFalse()
        ->and($created->hasVerifiedEmail())->toBeTrue()
        ->and($user->identities()->where('issuer', FIRST_PROOF_ISSUER)->count())->toBe(0);
    $this->assertAuthenticatedAs($created);
    firstProofMemberKeptEverything($account);
});

test('the provider with the verified address of a member connects the account and the challenge comes, as before: nothing is taken over', function () {
    $account = firstProofAccount(verified: true);
    $user = $account['user'];
    UserIdentity::factory()->create(['user_id' => $user->id, 'issuer' => FIRST_PROOF_OTHER_ISSUER, 'subject' => 'sub-of-the-member']);
    firstProofFakeProvider(FIRST_PROOF_EMAIL);

    firstProofSignInWithTheProvider()->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($account['secret'])])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $fresh = $user->fresh();
    expect(Hash::check(FIRST_PROOF_PASSWORD, (string) $fresh->password))->toBeTrue()
        ->and($fresh->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($fresh->session_version)->toBe(0)
        ->and($fresh->email_verified_at?->toDateTimeString())->toBe(FIRST_PROOF_VERIFIED_AT)
        ->and($user->tokens()->count())->toBe(1)
        ->and($user->identities()->orderBy('issuer')->pluck('issuer')->all())->toBe([FIRST_PROOF_ISSUER, FIRST_PROOF_OTHER_ISSUER])
        ->and(DB::table('sessions')->where('id', FIRST_PROOF_SESSION)->count())->toBe(1)
        ->and(User::query()->count())->toBe(1);
});

test('the provider takes over an account whose address was never verified, and verifies the address', function () {
    $user = firstProofAccount(verified: false)['user'];
    UserIdentity::factory()->create(['user_id' => $user->id, 'issuer' => FIRST_PROOF_OTHER_ISSUER, 'subject' => 'sub-of-before']);
    firstProofFakeProvider(FIRST_PROOF_EMAIL);

    firstProofSignInWithTheProvider()->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $fresh = $user->fresh();
    expect($fresh->email_verified_at?->toDateTimeString())->toBe(FIRST_PROOF_NOW)
        ->and($fresh->session_version)->toBe(1)
        ->and($fresh->password)->toBeNull()
        ->and($fresh->hasEnabledTwoFactorAuthentication())->toBeFalse()
        // The connection of before goes, the one of this sign-in comes
        ->and($user->identities()->pluck('issuer')->all())->toBe([FIRST_PROOF_ISSUER]);
});

// ---------------------------------------------------------------------------------------------------------------------
// The link of the address check
// ---------------------------------------------------------------------------------------------------------------------

test('the link of the address check verifies the address and changes nothing else: the person who opens it is signed in as the account', function () {
    $account = firstProofAccount(verified: false);
    $user = $account['user'];
    $before = $user->fresh();
    $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1(FIRST_PROOF_EMAIL)]);

    $this->actingAs($user)->get($link)->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    $fresh = $user->fresh();
    expect($fresh->email_verified_at?->toDateTimeString())->toBe(FIRST_PROOF_NOW)
        ->and($fresh->session_version)->toBe(0)
        ->and($fresh->password)->toBe($before->password)
        ->and($fresh->remember_token)->toBe(FIRST_PROOF_REMEMBER_TOKEN)
        ->and($fresh->two_factor_secret)->toBe($before->two_factor_secret)
        ->and(DB::table('sessions')->where('id', FIRST_PROOF_SESSION)->count())->toBe(1)
        ->and($user->tokens()->count())->toBe(1)
        ->and(DB::table('password_reset_tokens')->where('email', FIRST_PROOF_EMAIL)->count())->toBe(1);
});

// ---------------------------------------------------------------------------------------------------------------------
// What an account that never verified an address cannot do
// ---------------------------------------------------------------------------------------------------------------------

test('an account that never verified an address cannot change its password: the form gets the error of a wrong current password', function () {
    $user = firstProofAccount(verified: false)['user'];

    $this->actingAs($user->fresh())->put('/user/password', ['current_password' => FIRST_PROOF_PASSWORD, 'password' => FIRST_PROOF_NEW_PASSWORD, 'password_confirmation' => FIRST_PROOF_NEW_PASSWORD])
        ->assertRedirect()
        ->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'The provided password does not match your current password.']);

    expect(Hash::check(FIRST_PROOF_PASSWORD, (string) $user->fresh()?->password))->toBeTrue();
});

test('an account that never verified an address cannot turn the second factor on: "Enable" and new recovery codes answer 403, and the confirmation gets the error of a wrong code', function () {
    $user = User::factory()->unverified()->withUserProfile()->create(['email' => FIRST_PROOF_EMAIL, 'password' => Hash::make(FIRST_PROOF_PASSWORD)]);
    $this->actingAs($user->fresh())->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);

    $this->post('/user/two-factor-authentication')->assertForbidden();
    $this->post('/user/two-factor-recovery-codes')->assertForbidden();
    expect($user->fresh()?->two_factor_secret)->toBeNull()
        ->and($user->fresh()?->two_factor_recovery_codes)->toBeNull();

    // A secret that was stored before (an "Enable" of before this rule): its code is refused too
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user->forceFill(['two_factor_secret' => encrypt($secret)])->save();
    $this->post('/user/confirmed-two-factor-authentication', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertRedirect()
        ->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', ['code' => 'The provided two factor authentication code was invalid.']);

    expect($user->fresh()?->two_factor_confirmed_at)->toBeNull()
        ->and($user->fresh()?->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

test('a verified member changes the password, turns the second factor on and gets new recovery codes, also while a change of the address waits', function () {
    $user = User::factory()->withUserProfile()->create(['email' => FIRST_PROOF_EMAIL, 'password' => Hash::make(FIRST_PROOF_PASSWORD), 'email_verified_at' => Carbon::parse(FIRST_PROOF_VERIFIED_AT)]);
    $user->forceFill(['pending_email' => FIRST_PROOF_NEW_ADDRESS, 'pending_email_requested_at' => now()])->save();
    $this->actingAs($user->fresh())->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);

    $this->put('/user/password', ['current_password' => FIRST_PROOF_PASSWORD, 'password' => FIRST_PROOF_NEW_PASSWORD, 'password_confirmation' => FIRST_PROOF_NEW_PASSWORD])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->post('/user/two-factor-authentication')->assertRedirect()->assertSessionHasNoErrors();
    $secret = decrypt((string) $user->fresh()?->two_factor_secret);
    $this->post('/user/confirmed-two-factor-authentication', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect()->assertSessionHasNoErrors();
    $codes = $user->fresh()?->two_factor_recovery_codes;
    $this->post('/user/two-factor-recovery-codes')->assertRedirect()->assertSessionHasNoErrors();

    expect(Hash::check(FIRST_PROOF_NEW_PASSWORD, (string) $user->fresh()?->password))->toBeTrue()
        ->and($user->fresh()?->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($user->fresh()?->two_factor_recovery_codes)->not->toBe($codes);
});

test('the change of the password and the steps of the second factor read the model of the request: a model that never verified an address is refused', function () {
    $account = firstProofAccount(verified: false);
    $user = $account['user'];
    // A model that was read before the takeover
    $stale = User::query()->findOrFail($user->id);
    $this->actingAs($stale);

    expect(app(TakesOverUnverifiedAccounts::class)->takeOver($user, KnowiiAddressProof::SignInLink, FIRST_PROOF_EMAIL))->toBe(KnowiiTakeOverResult::TookOver);

    expect(fn () => app(UpdatesUserPasswords::class)->update($stale, ['current_password' => FIRST_PROOF_PASSWORD, 'password' => FIRST_PROOF_NEW_PASSWORD, 'password_confirmation' => FIRST_PROOF_NEW_PASSWORD]))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(ConfirmTwoFactorAuthentication::class)($stale, (new Google2FA)->getCurrentOtp($account['secret'])))
        ->toThrow(ValidationException::class)
        // An "Enable" or new recovery codes of a request that passed the session check before the takeover store nothing
        ->and(fn () => app(EnableTwoFactorAuthentication::class)($stale, true))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(GenerateNewRecoveryCodes::class)($stale))
        ->toThrow(AuthorizationException::class);

    $fresh = $user->fresh();
    expect($fresh->password)->toBeNull()
        ->and($fresh->two_factor_secret)->toBeNull()
        ->and($fresh->two_factor_recovery_codes)->toBeNull()
        ->and($fresh->two_factor_confirmed_at)->toBeNull();
});

// ---------------------------------------------------------------------------------------------------------------------
// The challenge of the second factor
// ---------------------------------------------------------------------------------------------------------------------

test('a challenge of the second factor that started before the takeover ends on the sign-in page, and forgets the account', function (string $answer) {
    $account = firstProofAccount(verified: false);

    // The first browser: the password, then the challenge
    firstProofNewBrowser();
    $response = $this->post('/login', ['email' => FIRST_PROOF_EMAIL, 'password' => FIRST_PROOF_PASSWORD, 'remember' => 'on'])->assertRedirect(route('two-factor.login'));
    $name = config()->string('session.cookie');
    $cookie = [$name => (string) $response->getCookie($name)?->getValue()];

    // The owner of the address uses a sign-in link in another browser
    firstProofNewBrowser();
    $this->post('/login/link/'.firstProofSignInLink())->assertRedirect(route('dashboard'));

    // The first browser opens the page of the challenge, or answers it: no error, the sign-in page
    firstProofNewBrowser();
    $this->withCookies($cookie);
    $response = match ($answer) {
        'the page' => $this->get('/two-factor-challenge'),
        'the code' => $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($account['secret'])]),
        'a recovery code' => $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-one']),
    };
    $response->assertRedirect(route('login'))
        ->assertSessionMissing('login.id')
        ->assertSessionMissing('login.remember')
        ->assertSessionMissing(FIRST_PROOF_HELD_LANDING);
    $this->assertGuest();
})->with(['the page', 'the code', 'a recovery code']);

// ---------------------------------------------------------------------------------------------------------------------
// The action
// ---------------------------------------------------------------------------------------------------------------------

test('the takeover removes the connections to a provider, with their entitlement keys, and the proof of a deletion', function () {
    $user = firstProofAccount(verified: false)['user'];
    $user->forceFill(['deletion_signed_in_at' => Carbon::parse(FIRST_PROOF_VERIFIED_AT)])->save();
    UserIdentity::factory()->create(['user_id' => $user->id, 'issuer' => FIRST_PROOF_ISSUER, 'subject' => 'sub-of-before']);
    UserIdentity::factory()->create(['user_id' => $user->id, 'issuer' => FIRST_PROOF_OTHER_ISSUER, 'subject' => 'sub-of-before']);
    UserEntitlement::query()->create(['user_id' => $user->id, 'issuer' => FIRST_PROOF_ISSUER, 'key' => 'example.paid']);
    $other = User::factory()->create(['name' => 'Grace Hopper', 'username' => 'grace-hopper', 'email' => 'grace@example.com']);
    UserIdentity::factory()->create(['user_id' => $other->id, 'issuer' => FIRST_PROOF_ISSUER, 'subject' => 'sub-grace']);
    $other->createToken('the token of another member', ['*']);

    $this->post('/login/link/'.firstProofSignInLink())->assertRedirect(route('dashboard'));

    expect($user->identities()->count())->toBe(0)
        ->and(UserEntitlement::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->deletion_signed_in_at)->toBeNull()
        // Nothing of another account changes
        ->and($other->identities()->count())->toBe(1)
        ->and($other->tokens()->count())->toBe(1)
        ->and($other->fresh()?->session_version)->toBe(0);
});

test('the takeover works on the stored row, read again and locked, and not on the model of the caller', function () {
    $user = User::factory()->unverified()->withUserProfile()->create(['email' => FIRST_PROOF_EMAIL, 'password' => Hash::make(FIRST_PROOF_PASSWORD)]);
    $stale = User::query()->findOrFail($user->id);
    // The row changes after the model was read
    DB::table('users')->where('id', $user->id)->update([
        'two_factor_secret' => encrypt('A-SECRET-OF-BEFORE'),
        'two_factor_recovery_codes' => encrypt('["recovery-one"]'),
        'two_factor_confirmed_at' => Carbon::parse(FIRST_PROOF_VERIFIED_AT),
    ]);

    expect(app(TakesOverUnverifiedAccounts::class)->takeOver($stale, KnowiiAddressProof::SignInLink, FIRST_PROOF_EMAIL))->toBe(KnowiiTakeOverResult::TookOver);

    $row = DB::table('users')->where('id', $user->id)->first();
    expect($row?->two_factor_secret)->toBeNull()
        ->and($row?->two_factor_recovery_codes)->toBeNull()
        ->and($row?->two_factor_confirmed_at)->toBeNull()
        ->and($row?->password)->toBeNull()
        ->and($row?->session_version)->toBe(1)
        // The model of the caller holds the stored values
        ->and($stale->session_version)->toBe(1)
        ->and($stale->hasVerifiedEmail())->toBeTrue()
        ->and($stale->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

test('the takeover changes nothing of an account whose stored row has a verified address, also when the model of the caller does not, and says so', function () {
    $user = firstProofAccount(verified: false)['user'];
    $stale = User::query()->findOrFail($user->id);
    DB::table('users')->where('id', $user->id)->update(['email_verified_at' => Carbon::parse(FIRST_PROOF_VERIFIED_AT)]);

    expect(app(TakesOverUnverifiedAccounts::class)->takeOver($stale, KnowiiAddressProof::SignInLink, FIRST_PROOF_EMAIL))->toBe(KnowiiTakeOverResult::AlreadyVerified)
        // The model of the caller holds the stored values
        ->and($stale->hasVerifiedEmail())->toBeTrue();

    $fresh = $user->fresh();
    expect(Hash::check(FIRST_PROOF_PASSWORD, (string) $fresh->password))->toBeTrue()
        ->and($fresh->session_version)->toBe(0)
        ->and($fresh->two_factor_secret)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(1);
});

test('each takeover writes one notice line after the commit, with its way and the cuid of the account, and no address', function (string $way, string $line) {
    $user = firstProofAccount(verified: false)['user'];
    UserIdentity::factory()->create(['user_id' => $user->id, 'issuer' => FIRST_PROOF_OTHER_ISSUER, 'subject' => 'sub-of-before']);
    // The level of the transaction of the test: a line that is written inside a transaction of the application has more
    $outside = DB::transactionLevel();
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = ['level' => $event->level, 'message' => $event->message, 'context' => $event->context, 'transactions' => DB::transactionLevel()];
    });

    if ($way === 'link') {
        $this->post('/login/link/'.firstProofSignInLink())->assertRedirect(route('dashboard'));
    } elseif ($way === 'reset') {
        $token = firstProofResetToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => FIRST_PROOF_EMAIL, 'password' => FIRST_PROOF_NEW_PASSWORD, 'password_confirmation' => FIRST_PROOF_NEW_PASSWORD])->assertRedirect(route('login'));
    } else {
        firstProofFakeProvider(FIRST_PROOF_EMAIL);
        firstProofSignInWithTheProvider()->assertRedirect(route('dashboard'));
    }

    $lines = $logged->getArrayCopy();
    $notices = array_values(array_filter($lines, fn (array $entry) => $entry['level'] === 'notice'));
    $unlinked = array_values(array_filter($lines, fn (array $entry) => $entry['message'] === 'Disconnected an OpenID Connect account'));
    expect($notices)->toHaveCount(1)
        ->and($notices[0]['message'])->toBe($line)
        ->and($notices[0]['context'])->toBe(['user' => $user->cuid])
        ->and($notices[0]['transactions'])->toBe($outside)
        ->and($unlinked)->toHaveCount(1)
        ->and($unlinked[0]['transactions'])->toBe($outside)
        ->and((string) json_encode($lines))->not->toContain(FIRST_PROOF_EMAIL);
})->with([
    'a sign-in link' => ['link', 'Sign-in link: took over an account whose address was never verified'],
    'a password reset' => ['reset', 'Password reset: took over an account whose address was never verified'],
    'the provider' => ['provider', 'OpenID Connect: took over an account whose address was never verified'],
]);

test('the takeover changes nothing of an account with a verified address', function () {
    $user = firstProofAccount(verified: true)['user'];
    $before = $user->fresh();

    expect(app(TakesOverUnverifiedAccounts::class)->takeOver($user, KnowiiAddressProof::PasswordReset, FIRST_PROOF_EMAIL, FIRST_PROOF_NEW_PASSWORD))->toBe(KnowiiTakeOverResult::AlreadyVerified);

    $fresh = $user->fresh();
    expect($fresh->password)->toBe($before->password)
        ->and($fresh->remember_token)->toBe(FIRST_PROOF_REMEMBER_TOKEN)
        ->and($fresh->two_factor_secret)->toBe($before->two_factor_secret)
        ->and($fresh->session_version)->toBe(0)
        ->and(DB::table('sessions')->where('id', FIRST_PROOF_SESSION)->count())->toBe(1)
        ->and($user->tokens()->count())->toBe(1)
        ->and(DB::table('password_reset_tokens')->where('email', FIRST_PROOF_EMAIL)->count())->toBe(1);
});

// ---------------------------------------------------------------------------------------------------------------------
// Two proofs at the same time
// ---------------------------------------------------------------------------------------------------------------------

/**
 * Another proof of the same address comes first: its takeover is stored right after the caller read the account, so the
 * caller holds a model whose address is not verified, and the stored row has a verified address, the password, the second
 * factor and the token of the account. The next read of the account by the caller (the lock of the takeover) sees it
 */
function firstProofAnotherProofComesFirst(User $user): void
{
    $done = new ArrayObject;
    User::retrieved(static function (User $read) use ($user, $done): void {
        if (count($done) === 0 && $read->is($user) && $read->email_verified_at === null) {
            $done[] = true;
            DB::table('users')->where('id', $user->id)->update(['email_verified_at' => Carbon::parse(FIRST_PROOF_NOW)]);
        }
    });
}

test('a proof that loses the race to another proof of the same address goes on as for a verified account, without an error', function (string $way) {
    $account = firstProofAccount(verified: false);
    $user = $account['user'];
    $before = $user->fresh();

    if ($way === 'link') {
        $token = firstProofSignInLink();
        firstProofAnotherProofComesFirst($user);
        // The second factor of the account stays: the challenge comes, as for every verified account
        $this->post('/login/link/'.$token)->assertRedirect(route('two-factor.login'));
    } elseif ($way === 'reset') {
        $token = firstProofResetToken($user);
        firstProofAnotherProofComesFirst($user);
        // The reset changes the password only, as for every verified account
        $this->post('/reset-password', ['token' => $token, 'email' => FIRST_PROOF_EMAIL, 'password' => FIRST_PROOF_NEW_PASSWORD, 'password_confirmation' => FIRST_PROOF_NEW_PASSWORD])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));
    } else {
        firstProofFakeProvider(FIRST_PROOF_EMAIL);
        firstProofAnotherProofComesFirst($user);
        // The provider connects the verified account, and the challenge comes (case 3)
        firstProofSignInWithTheProvider()->assertRedirect(route('two-factor.login'));
    }
    $this->assertGuest();

    $fresh = $user->fresh();
    expect($fresh->hasVerifiedEmail())->toBeTrue()
        // A reset ends every session of a verified account: the version goes up by 1 (ADR 0066)
        ->and($fresh->session_version)->toBe($way === 'reset' ? 1 : 0)
        ->and($fresh->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($fresh->two_factor_secret)->toBe($before->two_factor_secret)
        // A reset of the framework gives a new remember token to every account
        ->and($fresh->remember_token === FIRST_PROOF_REMEMBER_TOKEN)->toBe($way !== 'reset')
        ->and($user->tokens()->count())->toBe(1)
        ->and(DB::table('sessions')->where('id', FIRST_PROOF_SESSION)->count())->toBe(1)
        ->and(Hash::check($way === 'reset' ? FIRST_PROOF_NEW_PASSWORD : FIRST_PROOF_PASSWORD, (string) $fresh->password))->toBeTrue();
})->with([
    'a sign-in link' => ['link'],
    'a password reset' => ['reset'],
    'the provider' => ['provider'],
]);

// ---------------------------------------------------------------------------------------------------------------------
// A request that read the account before the takeover
// ---------------------------------------------------------------------------------------------------------------------

/**
 * An account whose address was never verified, the model that a request of the session of before read, and the takeover
 * by a sign-in link that comes after that read
 *
 * @return array{user: User, stale: User}
 */
function firstProofStaleModelAfterATakeover(): array
{
    $user = firstProofAccount(verified: false)['user'];
    $stale = User::query()->findOrFail($user->id);
    expect(app(TakesOverUnverifiedAccounts::class)->takeOver(User::query()->findOrFail($user->id), KnowiiAddressProof::SignInLink, FIRST_PROOF_EMAIL))->toBe(KnowiiTakeOverResult::TookOver);

    return ['user' => $user, 'stale' => $stale];
}

test('a model read before the takeover writes nothing after it: "Log out other browser sessions" does not store the password again', function () {
    ['user' => $user, 'stale' => $stale] = firstProofStaleModelAfterATakeover();
    $this->actingAs($stale);

    // The step of the controller of Jetstream after its check of the password
    Auth::guard('web')->logoutOtherDevices(FIRST_PROOF_PASSWORD);

    expect($user->fresh()->password)->toBeNull()
        ->and($user->fresh()->session_version)->toBe(1);
    firstProofPasswordIsRefused(FIRST_PROOF_PASSWORD);
});

test('a model read before the takeover writes nothing after it: the password that the sign-in hashes again, and the remember token', function () {
    ['user' => $user, 'stale' => $stale] = firstProofStaleModelAfterATakeover();
    $token = $user->fresh()->remember_token;

    Auth::getProvider()->rehashPasswordIfRequired($stale, ['password' => FIRST_PROOF_PASSWORD], true);
    Auth::getProvider()->updateRememberToken($stale, 'a-remember-token-of-a-session-of-before');

    expect($user->fresh()->password)->toBeNull()
        ->and($user->fresh()->remember_token)->toBe($token);
});

test('a model read before the takeover writes nothing after it: a change of the profile, of the address and of the password', function () {
    Notification::fake();
    ['user' => $user, 'stale' => $stale] = firstProofStaleModelAfterATakeover();
    // Three requests of before, each with its own model
    $forTheZone = clone $stale;
    $forThePassword = clone $stale;

    app(UpdatesTimeZones::class)->update($forTheZone, ['time_zone' => 'Europe/Brussels']);
    // A write of the password by a model of before, as any change of the password stores it
    $forThePassword->forceFill(['password' => Hash::make(FIRST_PROOF_NEW_PASSWORD)])->save();
    // The correction of the address that was never verified finds the row verified: it changes nothing
    app(UpdatesUserProfileInformation::class)->update($stale, ['name' => 'Ada Lovelace', 'email' => 'another.address@example.org', 'current_password' => FIRST_PROOF_PASSWORD]);

    $fresh = $user->fresh();
    expect($fresh->time_zone)->toBeNull()
        ->and($fresh->email)->toBe(FIRST_PROOF_EMAIL)
        ->and($fresh->email_verified_at?->toDateTimeString())->toBe(FIRST_PROOF_NOW)
        ->and($fresh->password)->toBeNull()
        ->and($fresh->session_version)->toBe(1);
    Notification::assertNothingSent();
});

test('a model read after the takeover writes as before: the profile, the password, "Log out other browser sessions" and the remember token', function () {
    ['user' => $user] = firstProofStaleModelAfterATakeover();
    $fresh = User::query()->findOrFail($user->id);

    app(UpdatesTimeZones::class)->update($fresh, ['time_zone' => 'Europe/Brussels']);
    $fresh->forceFill(['password' => Hash::make(FIRST_PROOF_NEW_PASSWORD)])->save();
    $this->actingAs($fresh);
    Auth::guard('web')->logoutOtherDevices(FIRST_PROOF_NEW_PASSWORD);
    Auth::getProvider()->updateRememberToken($fresh, 'a-remember-token-of-now');

    $row = $user->fresh();
    expect($row->time_zone)->toBe('Europe/Brussels')
        ->and(Hash::check(FIRST_PROOF_NEW_PASSWORD, (string) $row->password))->toBeTrue()
        ->and($row->remember_token)->toBe('a-remember-token-of-now')
        ->and($row->session_version)->toBe(1);
});

test('a model of a new account writes as before: it holds the version of the sessions from its first write', function () {
    $user = User::factory()->create(['email' => FIRST_PROOF_EMAIL]);

    expect($user->getRawOriginal('session_version'))->toBe(0);
    $user->forceFill(['time_zone' => 'Europe/Brussels'])->save();

    expect($user->fresh()->time_zone)->toBe('Europe/Brussels');
});

// ---------------------------------------------------------------------------------------------------------------------
// The deletion of the account by a request that read the account before the takeover
// ---------------------------------------------------------------------------------------------------------------------

/**
 * An account whose address was never verified, with a password and without the second factor: its deletion needs the
 * password only
 */
function firstProofAccountToDelete(): User
{
    $user = firstProofAccount(verified: false)['user'];
    $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();

    return User::query()->findOrFail($user->id);
}

/**
 * The takeover by a sign-in link, stored in another request
 */
function firstProofTakeOver(int $id): void
{
    expect(app(TakesOverUnverifiedAccounts::class)->takeOver(User::query()->findOrFail($id), KnowiiAddressProof::SignInLink, FIRST_PROOF_EMAIL))->toBe(KnowiiTakeOverResult::TookOver);
}

test('a deletion whose password check read the row before a takeover deletes nothing: the error of a wrong password', function () {
    $user = firstProofAccountToDelete();
    // The takeover is stored right after the password check read the row by its address
    $done = false;
    DB::listen(static function (QueryExecuted $query) use ($user, &$done): void {
        if (! $done && str_starts_with($query->sql, 'select * from "users" where') && str_contains($query->sql, '"email"') && in_array(FIRST_PROOF_EMAIL, $query->bindings, true)) {
            $done = true;
            firstProofTakeOver($user->id);
        }
    });

    $this->actingAs($user)->delete(route('current-user.destroy'), ['password' => FIRST_PROOF_PASSWORD])
        ->assertSessionHasErrors(['password' => 'The password is incorrect.']);

    expect($done)->toBeTrue();
    $row = User::query()->find($user->id);
    expect($row)->not->toBeNull()
        ->and($row?->session_version)->toBe(1)
        ->and($row?->hasVerifiedEmail())->toBeTrue()
        ->and($row?->profile)->not->toBeNull();
});

test('a deletion whose password check passed before a takeover deletes nothing: the error of a wrong password', function () {
    $user = firstProofAccountToDelete();
    // The password check of the framework passes on the row of before, then the takeover is stored
    app()->instance(ConfirmPassword::class, new class($user->id) extends ConfirmPassword
    {
        public function __construct(private readonly int $id) {}

        public function __invoke(StatefulGuard $guard, $user, ?string $password = null)
        {
            $confirmed = parent::__invoke($guard, $user, $password);
            firstProofTakeOver($this->id);

            return $confirmed;
        }
    });

    $this->actingAs($user)->delete(route('current-user.destroy'), ['password' => FIRST_PROOF_PASSWORD])
        ->assertSessionHasErrors(['password' => 'The password is incorrect.']);

    $row = User::query()->find($user->id);
    expect($row)->not->toBeNull()
        ->and($row?->session_version)->toBe(1)
        ->and($row?->profile)->not->toBeNull();
});

test('the API refuses the deletion of an account whose address was never verified: 403, as every route of the account', function () {
    $user = firstProofAccountToDelete();
    Sanctum::actingAs($user, ['account:delete']);

    $this->deleteJson(ApiRoutes::path(ApiRoutes::USER), ['password' => FIRST_PROOF_PASSWORD])->assertForbidden();

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

test('the deletion deletes the account of a model with the version of the row, and refuses a model of another version', function () {
    $user = firstProofAccountToDelete();
    $stale = User::query()->findOrFail($user->id);
    firstProofTakeOver($user->id);

    expect(fn () => app(DeletesAccounts::class)->delete($stale))->toThrow(ValidationException::class, 'The password is incorrect.');
    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();

    app(DeletesAccounts::class)->delete(User::query()->findOrFail($user->id));
    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------------------------------------------------
// A correction of the address and a proof of the address of before
// ---------------------------------------------------------------------------------------------------------------------

/**
 * The correction of the address comes in between: it is stored right after the proof read the account with the address
 * of before (the first read of the account with that address)
 */
function firstProofACorrectionComesInBetween(User $user, string $corrected): void
{
    $done = new ArrayObject;
    User::retrieved(static function (User $read) use ($user, $done, $corrected): void {
        if (count($done) === 0 && $read->is($user) && $read->email === FIRST_PROOF_EMAIL) {
            $done[] = true;
            DB::table('users')->where('id', $user->id)->update(['email' => $corrected]);
        }
    });
}

/**
 * Nothing of the account changed, apart from the corrected address: it is still not verified, and the password, the second
 * factor, the token, the session and the version of the sessions stay
 *
 * @param  array{user: User, secret: string, apiToken: string}  $account
 */
function firstProofOnlyTheAddressChanged(array $account, string $corrected): void
{
    $fresh = $account['user']->fresh();
    expect($fresh->email)->toBe($corrected)
        ->and($fresh->email_verified_at)->toBeNull()
        ->and(Hash::check(FIRST_PROOF_PASSWORD, (string) $fresh->password))->toBeTrue()
        ->and($fresh->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($fresh->session_version)->toBe(0)
        ->and($account['user']->tokens()->count())->toBe(1)
        ->and(DB::table('sessions')->where('id', FIRST_PROOF_SESSION)->count())->toBe(1);
}

test('the takeover needs the address that was proved: a row with another address, in any case, is not taken over', function () {
    $account = firstProofAccount(verified: false);
    $user = $account['user'];

    expect(app(TakesOverUnverifiedAccounts::class)->takeOver($user, KnowiiAddressProof::Provider, 'another.address@example.org'))->toBe(KnowiiTakeOverResult::OtherAddress)
        // The model of the caller holds the values of the row
        ->and($user->email)->toBe(FIRST_PROOF_EMAIL);
    firstProofOnlyTheAddressChanged($account, FIRST_PROOF_EMAIL);

    // The address in another case is the address of the account
    expect(app(TakesOverUnverifiedAccounts::class)->takeOver($user, KnowiiAddressProof::Provider, 'ADA@Example.com'))->toBe(KnowiiTakeOverResult::TookOver)
        ->and($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a proof of the address of before while a correction waits takes the account over, and the pending address goes', function () {
    $account = firstProofAccount(verified: false);
    $user = $account['user'];
    // The model of the proof, read before the correction
    $stale = User::query()->findOrFail($user->id);
    Notification::fake();
    // The correction waits for the link of the new address (ADR 0058): the account keeps the address of before
    app(UpdatesUserProfileInformation::class)->update(User::query()->findOrFail($user->id), ['name' => 'Ada Lovelace', 'email' => 'ada.corrected@example.org', 'current_password' => FIRST_PROOF_PASSWORD]);
    expect($user->fresh()->pending_email)->toBe('ada.corrected@example.org');

    expect(app(TakesOverUnverifiedAccounts::class)->takeOver($stale, KnowiiAddressProof::SignInLink, FIRST_PROOF_EMAIL))->toBe(KnowiiTakeOverResult::TookOver)
        ->and($stale->email)->toBe(FIRST_PROOF_EMAIL);
    $fresh = $user->fresh();
    expect($fresh->hasVerifiedEmail())->toBeTrue()
        ->and($fresh->pending_email)->toBeNull()
        ->and($fresh->password)->toBeNull();
});

test('a sign-in link of the address of before, used while a correction is stored, answers as a link that does not work', function () {
    $account = firstProofAccount(verified: false);
    $token = firstProofSignInLink();
    firstProofNewBrowser();
    firstProofACorrectionComesInBetween($account['user'], 'ada.corrected@example.org');

    $this->post('/login/link/'.$token)
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'This sign-in link expired or was already used. Ask for a new one.');

    $this->assertGuest();
    firstProofOnlyTheAddressChanged($account, 'ada.corrected@example.org');
});

test('a password reset of the address of before, sent while a correction is stored, answers as a token that does not work', function () {
    $account = firstProofAccount(verified: false);
    $token = firstProofResetToken($account['user']);
    firstProofNewBrowser();
    firstProofACorrectionComesInBetween($account['user'], 'ada.corrected@example.org');

    $this->post('/reset-password', ['token' => $token, 'email' => FIRST_PROOF_EMAIL, 'password' => FIRST_PROOF_NEW_PASSWORD, 'password_confirmation' => FIRST_PROOF_NEW_PASSWORD])
        ->assertRedirect()
        ->assertSessionHasErrors(['email' => FailedPasswordResetResponse::MESSAGE]);

    $this->assertGuest();
    firstProofOnlyTheAddressChanged($account, 'ada.corrected@example.org');
});

test('a row with another address that is verified now is another address for the takeover, not a verified one', function () {
    $account = firstProofAccount(verified: false);
    $stale = User::query()->findOrFail($account['user']->id);
    // A correction of the address, then the address check of the new address, after the proof read the account
    DB::table('users')->where('id', $stale->id)->update(['email' => 'ada.corrected@example.org', 'email_verified_at' => '2026-11-20 09:30:00']);

    expect(app(TakesOverUnverifiedAccounts::class)->takeOver($stale, KnowiiAddressProof::PasswordReset, FIRST_PROOF_EMAIL, FIRST_PROOF_NEW_PASSWORD))->toBe(KnowiiTakeOverResult::OtherAddress)
        ->and(Hash::check(FIRST_PROOF_PASSWORD, (string) $account['user']->fresh()->password))->toBeTrue()
        ->and($account['user']->fresh()->session_version)->toBe(0);
});

// ---------------------------------------------------------------------------------------------------------------------
// A proof for an account that is gone
// ---------------------------------------------------------------------------------------------------------------------

/**
 * The account is deleted in between: the deletion is stored, with a model of its own, right after the proof read the table
 * (the account for a sign-in link and the provider, the token of the reset mail for a password reset)
 */
function firstProofTheAccountIsDeletedAfterTheReadOf(User $user, string $table): ArrayObject
{
    $done = new ArrayObject;
    DB::listen(static function (QueryExecuted $query) use ($user, $table, $done): void {
        if (count($done) === 0 && str_starts_with($query->sql, 'select * from "'.$table.'"')) {
            $done[] = true;
            Notification::fake();
            app(DeletesAccounts::class)->delete(User::query()->findOrFail($user->id), ['password' => FIRST_PROOF_PASSWORD]);
        }
    });

    return $done;
}

test('the takeover of an account that is gone changes nothing and says so: the model of the caller stays as it is', function () {
    $user = firstProofAccount(verified: false)['user'];
    $stale = User::query()->findOrFail($user->id);
    User::query()->whereKey($user->id)->delete();

    expect(app(TakesOverUnverifiedAccounts::class)->takeOver($stale, KnowiiAddressProof::SignInLink, FIRST_PROOF_EMAIL))->toBe(KnowiiTakeOverResult::Gone)
        ->and($stale->exists)->toBeTrue()
        ->and($stale->addressWasNeverVerified())->toBeTrue()
        ->and(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

test('a model of an account that is gone writes nothing when it is saved after the takeover', function () {
    $user = firstProofAccount(verified: false)['user'];
    $stale = User::query()->findOrFail($user->id);
    User::query()->whereKey($user->id)->delete();

    expect(app(TakesOverUnverifiedAccounts::class)->takeOver($stale, KnowiiAddressProof::SignInLink, FIRST_PROOF_EMAIL))->toBe(KnowiiTakeOverResult::Gone);
    $stale->forceFill(['remember_token' => 'a-remember-token-after-the-takeover'])->save();

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(User::query()->count())->toBe(0);
});

test('a sign-in link of an account that is deleted before the link is used answers as a link that expired, and signs nobody in', function () {
    $account = firstProofAccount(verified: false);
    $token = firstProofSignInLink();
    firstProofNewBrowser();
    $deleted = firstProofTheAccountIsDeletedAfterTheReadOf($account['user'], 'users');

    $this->post('/login/link/'.$token)
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'This sign-in link expired or was already used. Ask for a new one.');

    $this->assertGuest();
    expect(count($deleted))->toBe(1)
        ->and(User::query()->whereKey($account['user']->id)->exists())->toBeFalse()
        ->and(User::query()->count())->toBe(0);
});

test('a sign-in link of a verified account that is deleted before the link is used answers as a link that expired, and signs nobody in', function () {
    firstProofSessionDriver('database');
    $user = firstProofAccount(verified: true)['user'];
    // Without the second factor: the link signs in at once
    $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
    $token = firstProofSignInLink();
    firstProofNewBrowser();
    $deleted = firstProofTheAccountIsDeletedAfterTheReadOf($user, 'users');

    $this->post('/login/link/'.$token)
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'This sign-in link expired or was already used. Ask for a new one.');

    // No session holds the account: neither the session of this request nor a stored one
    $this->assertGuest();
    expect(count($deleted))->toBe(1)
        ->and(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(array_values(array_filter(array_keys(session()->all()), static fn (string $key): bool => str_starts_with($key, 'login_'))))->toBe([])
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

test('a sign-in link of a verified account with the second factor that is deleted before the link is used answers as a link that expired, and starts no challenge', function () {
    $user = firstProofAccount(verified: true)['user'];
    $token = firstProofSignInLink();
    firstProofNewBrowser();
    $deleted = firstProofTheAccountIsDeletedAfterTheReadOf($user, 'users');

    $this->post('/login/link/'.$token)
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'This sign-in link expired or was already used. Ask for a new one.')
        ->assertSessionMissing('login.id');

    $this->assertGuest();
    expect(count($deleted))->toBe(1)
        ->and(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

test('a sign-in link of an account that is deleted after the takeover and before the sign-in answers as a link that expired, and signs nobody in', function () {
    firstProofSessionDriver('database');
    $user = firstProofAccount(verified: false)['user'];
    // The takeover is stored, and its live connections end: the account is deleted then, before the link signs in
    $ended = new ArrayObject;
    app()->instance(EndsLiveConnections::class, new class($ended) implements EndsLiveConnections
    {
        public function __construct(private readonly ArrayObject $ended) {}

        public function endFor(string $userCuid): void
        {
            $this->ended[] = $userCuid;
            User::query()->where('cuid', $userCuid)->delete();
        }
    });
    $token = firstProofSignInLink();
    firstProofNewBrowser();

    $this->post('/login/link/'.$token)
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'This sign-in link expired or was already used. Ask for a new one.');

    // The takeover took the account over; then no session holds the account, neither this one nor a stored one
    $this->assertGuest();
    expect($ended->getArrayCopy())->toBe([$user->cuid])
        ->and(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(array_values(array_filter(array_keys(session()->all()), static fn (string $key): bool => str_starts_with($key, 'login'))))->toBe([])
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

test('a password reset of an account that is deleted before the reset is stored answers as a token that does not work, and signs nobody in', function () {
    $account = firstProofAccount(verified: false);
    $token = firstProofResetToken($account['user']);
    firstProofNewBrowser();
    $deleted = firstProofTheAccountIsDeletedAfterTheReadOf($account['user'], 'password_reset_tokens');

    $this->post('/reset-password', ['token' => $token, 'email' => FIRST_PROOF_EMAIL, 'password' => FIRST_PROOF_NEW_PASSWORD, 'password_confirmation' => FIRST_PROOF_NEW_PASSWORD])
        ->assertRedirect()
        ->assertSessionHasErrors(['email' => FailedPasswordResetResponse::MESSAGE]);

    $this->assertGuest();
    expect(count($deleted))->toBe(1)
        ->and(User::query()->whereKey($account['user']->id)->exists())->toBeFalse()
        ->and(User::query()->count())->toBe(0);
});

test('the provider with the address of an account that is gone at the takeover takes the path of an address without an account: a new account', function () {
    $account = firstProofAccount(verified: false);
    firstProofFakeProvider(FIRST_PROOF_EMAIL);
    $deleted = firstProofTheAccountIsDeletedAfterTheReadOf($account['user'], 'users');

    firstProofSignInWithTheProvider()->assertRedirect();

    $created = User::query()->sole();
    expect(count($deleted))->toBe(1)
        ->and($created->id)->not->toBe($account['user']->id)
        ->and($created->email)->toBe(FIRST_PROOF_EMAIL)
        ->and($created->hasVerifiedEmail())->toBeTrue()
        ->and($created->password)->toBeNull()
        ->and(UserIdentity::query()->where('user_id', $created->id)->where('issuer', FIRST_PROOF_ISSUER)->count())->toBe(1);
    $this->assertAuthenticatedAs($created);
});

// ---------------------------------------------------------------------------------------------------------------------
// The live connections
// ---------------------------------------------------------------------------------------------------------------------

test('a takeover ends the live connections of the account after the commit; a proof of a verified account ends none', function () {
    $calls = new ArrayObject;
    $outside = DB::transactionLevel();
    app()->instance(EndsLiveConnections::class, new class($calls) implements EndsLiveConnections
    {
        public function __construct(private readonly ArrayObject $calls) {}

        public function endFor(string $userCuid): void
        {
            $this->calls[] = ['user' => $userCuid, 'transactions' => DB::transactionLevel()];
        }
    });

    $user = firstProofAccount(verified: false)['user'];
    $this->post('/login/link/'.firstProofSignInLink())->assertRedirect(route('dashboard'));
    expect($calls->getArrayCopy())->toBe([['user' => $user->cuid, 'transactions' => $outside]]);

    // A sign-in link of a verified account takes nothing over
    firstProofNewBrowser();
    $this->post('/login/link/'.firstProofSignInLink())->assertRedirect(route('dashboard'));
    expect($calls)->toHaveCount(1);
});

/**
 * Reverb as the broadcaster of the instance, with a client of the HTTP API whose answers the test gives. Returns the
 * requests that the client sent, with their options
 *
 * @param  list<Response|Throwable>  $answers
 */
function firstProofReverb(array $answers): ArrayObject
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => ['host' => 'reverb.example.test', 'port' => 443, 'scheme' => 'https', 'useTLS' => true],
            'client_options' => [],
        ],
    ]);
    $requests = new ArrayObject;
    $stack = HandlerStack::create(new MockHandler($answers));
    $stack->push(Middleware::history($requests));
    app()->instance(EndsLiveConnections::class, new ReverbLiveConnections(['handler' => $stack]));

    return $requests;
}

/**
 * The lines of the log from now on
 */
function firstProofLogLines(): ArrayObject
{
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = ['level' => $event->level, 'message' => $event->message, 'context' => $event->context];
    });

    return $logged;
}

test('Reverb gets the call that ends the connections of the account, with a timeout of 2 seconds', function () {
    $requests = firstProofReverb([new Response(200, [], '{}')]);

    app(EndsLiveConnections::class)->endFor('cuid-of-the-account');

    expect($requests)->toHaveCount(1)
        ->and($requests[0]['request']->getMethod())->toBe('POST')
        ->and($requests[0]['request']->getUri()->getHost())->toBe('reverb.example.test')
        ->and($requests[0]['request']->getUri()->getPath())->toBe('/apps/test-app/users/cuid-of-the-account/terminate_connections')
        ->and($requests[0]['options']['timeout'])->toBe(ReverbLiveConnections::TIMEOUT_SECONDS)
        ->and($requests[0]['options']['connect_timeout'])->toBe(ReverbLiveConnections::TIMEOUT_SECONDS)
        ->and(ReverbLiveConnections::TIMEOUT_SECONDS)->toBe(2);
});

test('a failure of Reverb is one line of the log, never an error: an error answer, or no answer in time', function (Response|Throwable $answer) {
    $requests = firstProofReverb([$answer]);
    $logged = firstProofLogLines();

    app(EndsLiveConnections::class)->endFor('cuid-of-the-account');

    expect($requests)->toHaveCount(1)
        ->and($logged->getArrayCopy())->toHaveCount(1)
        ->and($logged[0]['level'])->toBe('warning')
        ->and($logged[0]['message'])->toBe('The WebSocket server did not end the connections of an account')
        ->and($logged[0]['context']['user'])->toBe('cuid-of-the-account');
})->with([
    'an error answer' => fn () => new Response(500, [], 'down'),
    'no answer in time' => fn () => new ConnectException('Connection timed out after 2000 milliseconds', new GuzzleRequest('POST', 'https://reverb.example.test')),
]);

test('another broadcaster gets no call', function () {
    $unused = firstProofReverb([]);
    config(['broadcasting.default' => 'null']);

    app(EndsLiveConnections::class)->endFor('cuid-of-the-account');

    expect($unused)->toHaveCount(0);
});

// ---------------------------------------------------------------------------------------------------------------------
// The column of the version of the sessions
// ---------------------------------------------------------------------------------------------------------------------

test('the migration gives every account the version 0 of the sessions', function () {
    $verified = User::factory()->create(['email' => FIRST_PROOF_EMAIL, 'email_verified_at' => Carbon::parse(FIRST_PROOF_VERIFIED_AT)]);
    $never = User::factory()->unverified()->create(['email' => 'grace@example.com']);
    $migration = require database_path('migrations/'.FIRST_PROOF_MIGRATION);

    $migration->down();
    expect(Schema::hasColumn('users', 'session_version'))->toBeFalse();
    $migration->up();

    $rows = DB::table('users')->orderBy('id')->get(['id', 'session_version'])->keyBy('id');
    expect($rows[$verified->id]->session_version)->toBe(0)
        ->and($rows[$never->id]->session_version)->toBe(0)
        ->and(Schema::hasColumn('users', 'email_first_verified_at'))->toBeFalse();
});

test('the version of the sessions is in no answer of the API and in no page', function () {
    $user = firstProofAccount(verified: true)['user'];
    $this->actingAs($user->fresh());

    $reads = [
        $this->getJson('/api/v1/user')->assertOk()->getContent(),
        $this->get('/user/profile')->assertOk()->getContent(),
    ];
    expect($user->fresh()?->toArray())->not->toHaveKey('session_version');
    foreach ($reads as $read) {
        expect((string) $read)->not->toContain('session_version');
    }
});
