<?php

use App\Contracts\Auth\ReadsOpenIdConnectIdentities;
use App\Http\Middleware\EndOutdatedSessions;
use App\Mail\MagicLink;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use App\Support\OpenIdIdentity;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
 * The version of the sessions (ADR 0059): each session holds the version of its account from its sign-in, and a session
 * whose version differs from the one of the account ends at its next request. A takeover adds 1, so it ends every session
 * of the account, whatever the session driver. The tests use real cookies: the cookie of the session and the remember
 * cookie that a sign-in gave, sent by a browser that holds nothing else.
 */

const SV_EMAIL = 'ada@example.com';

const SV_PASSWORD = 'the-password-of-the-sign-up-2026';

const SV_NEW_PASSWORD = 'A-new-password-of-2026!';

const SV_ISSUER = 'https://auth.example.test';

const SV_NOW = '2026-11-21 10:00:00';

/**
 * The session driver of the test, for every request that comes next. The handler proves it
 */
function svSessionDriver(string $driver): void
{
    config(['session.driver' => $driver]);
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    app('auth')->forgetGuards();

    expect(app('session')->driver()->getHandler())->toBeInstanceOf($driver === 'database' ? DatabaseSessionHandler::class : ArraySessionHandler::class);
}

/**
 * The authorization of a private channel, with a broadcaster that checks the channels (the one of the tests answers yes to
 * everything): it needs a person who is signed in. The broadcaster of the tests comes back after it: the events of the
 * other requests go nowhere
 */
function svAuthorizeChannel(): TestResponse
{
    $default = config('broadcasting.default');
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => ['host' => 'localhost', 'port' => 443, 'scheme' => 'https', 'useTLS' => true],
        ],
    ]);
    app(BroadcastManager::class)->forgetDrivers();
    // The channels go to the broadcaster of now
    require base_path('routes/channels.php');

    try {
        return test()->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-communities']);
    } finally {
        config(['broadcasting.default' => $default]);
        app(BroadcastManager::class)->forgetDrivers();
    }
}

/**
 * Another browser: it sends no cookie, and the memory of the test holds no session and no signed-in account. The sessions
 * that the store holds stay
 */
function svNewBrowser(): void
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
 * An account as a sign-up makes it: a password, an address that is not verified, a personal community
 */
function svSignedUpAccount(): User
{
    return User::factory()->unverified()->withUserProfile()->withPersonalCommunity()->create([
        'name' => 'Ada Lovelace',
        'username' => 'ada-lovelace',
        'email' => SV_EMAIL,
        'password' => Hash::make(SV_PASSWORD),
    ]);
}

/**
 * The person signs in with the password and "Remember me", in a new browser: the cookies that the browser keeps
 *
 * @return array{session: array<string, string>, remember: array<string, string>}
 */
function svSignInWithPassword(): array
{
    svNewBrowser();
    $response = test()->post('/login', ['email' => SV_EMAIL, 'password' => SV_PASSWORD, 'remember' => 'on']);
    $response->assertRedirect();
    test()->assertAuthenticated();

    $sessionName = config()->string('session.cookie');
    $rememberName = Auth::guard('web')->getRecallerName();
    $session = $response->getCookie($sessionName)?->getValue();
    $remember = $response->getCookie($rememberName)?->getValue();
    expect($session)->toBeString()->not->toBe('')
        ->and($remember)->toBeString()->not->toBe('');

    return ['session' => [$sessionName => (string) $session], 'remember' => [$rememberName => (string) $remember]];
}

/**
 * The request of a browser that holds only these cookies. The test client encrypts them as the application does
 *
 * @param  array<string, string>  $cookies
 */
function svRequest(string $endpoint, array $cookies): TestResponse
{
    svNewBrowser();
    // A request for JSON sends the cookies only with the credentials, as a browser does for its own site
    test()->withCookies($cookies)->withCredentials();

    return match ($endpoint) {
        'page' => test()->get('/user/profile'),
        'fortify' => test()->putJson('/user/profile-information', ['name' => 'Another Name', 'email' => SV_EMAIL]),
        'api' => test()->withHeaders(['Referer' => config('app.url'), 'Origin' => config('app.url')])->getJson('/api/v1/user'),
        'broadcast' => svAuthorizeChannel(),
    };
}

/**
 * The provider of the instance, faked: it vouches for the address of the account
 */
function svFakeProvider(): void
{
    config(['services.oidc' => ['issuer' => SV_ISSUER, 'client_id' => 'knowii-test-client', 'client_secret' => 'a-secret-that-is-not-real', 'scopes' => 'openid profile email', 'label' => 'Example account']]);
    app()->instance(ReadsOpenIdConnectIdentities::class, new class implements ReadsOpenIdConnectIdentities
    {
        public function authorizationUrl(string $state, string $nonce, string $codeChallenge, bool $freshSignIn = false): string
        {
            return SV_ISSUER.'/authorize?state='.$state;
        }

        public function identity(string $code, string $codeVerifier, string $nonce): OpenIdIdentity
        {
            return new OpenIdIdentity(SV_ISSUER, 'sub-of-the-owner', SV_EMAIL, true, 'Ada Lovelace');
        }
    });
}

/**
 * The owner of the address proves it in a new browser, in one of the three ways: the account is taken over
 */
function svTakeOver(string $way): void
{
    svNewBrowser();
    if ($way === 'link') {
        Mail::fake();
        test()->post('/login/link', ['email' => SV_EMAIL])->assertRedirect(route('login'));
        $token = '';
        Mail::assertQueued(MagicLink::class, function (MagicLink $mail) use (&$token) {
            $token = $mail->token;

            return true;
        });
        test()->post('/login/link/'.$token)->assertRedirect(route('dashboard'));
    } elseif ($way === 'reset') {
        Notification::fake();
        test()->post('/forgot-password', ['email' => SV_EMAIL])->assertSessionHasNoErrors();
        $token = '';
        Notification::assertSentTo(User::query()->where('email', SV_EMAIL)->firstOrFail(), ResetPasswordLink::class, function (ResetPasswordLink $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        test()->post('/reset-password', ['token' => $token, 'email' => SV_EMAIL, 'password' => SV_NEW_PASSWORD, 'password_confirmation' => SV_NEW_PASSWORD])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));
    } else {
        svFakeProvider();
        $start = test()->get('/auth/oidc/redirect');
        $start->assertRedirect();
        parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $query);
        test()->get('/auth/oidc/callback?code=c&state='.($query['state'] ?? ''))->assertRedirect(route('dashboard'));
    }

    $account = User::query()->where('email', SV_EMAIL)->firstOrFail();
    expect($account->hasVerifiedEmail())->toBeTrue()
        ->and($account->session_version)->toBe(1);
}

beforeEach(function () {
    $this->travelTo(Carbon::parse(SV_NOW));
});

// ---------------------------------------------------------------------------------------------------------------------
// A takeover ends every session of the account
// ---------------------------------------------------------------------------------------------------------------------

test('a takeover ends the session of before, with its cookie or with the remember cookie, whatever the session driver', function (string $driver, string $way, string $credential, string $endpoint) {
    svSessionDriver($driver);
    $account = svSignedUpAccount();
    $cookies = svSignInWithPassword()[$credential];

    // Before: the first browser is signed in as the account
    $before = svRequest($endpoint, $cookies);
    match ($endpoint) {
        'page' => $before->assertOk(),
        'fortify' => $before->assertOk(),
        // The API needs a verified address: signed in, and refused for that
        'api' => $before->assertForbidden(),
        'broadcast' => $before->assertOk()->assertJsonStructure(['auth']),
    };
    test()->assertAuthenticatedAs($account);

    svTakeOver($way);

    // After: the same browser is signed out, and changes nothing
    $after = svRequest($endpoint, $cookies);
    match ($endpoint) {
        'page' => $after->assertRedirect(route('login')),
        'fortify', 'api' => $after->assertUnauthorized(),
        // 401 when the version ends the session (the array driver keeps it, with the cookie of the session), 403 when the
        // session is gone already (the takeover deleted its row, or only the remember cookie came): nobody is signed in
        'broadcast' => $after->assertStatus($driver === 'array' && $credential === 'session' ? 401 : 403)->assertJsonMissingPath('auth'),
    };
    test()->assertGuest();
    expect($account->fresh()?->name)->toBe('Ada Lovelace')
        ->and($account->fresh()?->hasVerifiedEmail())->toBeTrue();
})->with([
    'the database driver' => ['database'],
    'the array driver' => ['array'],
])->with([
    'a sign-in link' => ['link'],
    'a password reset' => ['reset'],
    'the provider' => ['provider'],
])->with([
    'the cookie of the session' => ['session'],
    'the remember cookie' => ['remember'],
])->with([
    'a page' => ['page'],
    'a route of Fortify' => ['fortify'],
    'the API of the session' => ['api'],
    'the authorization of a channel' => ['broadcast'],
]);

test('with the array driver the session of before still exists after a takeover: its version ends it, and it is invalidated', function () {
    svSessionDriver('array');
    svSignedUpAccount();
    $cookies = svSignInWithPassword()['session'];
    $id = (string) reset($cookies);
    // The session is encrypted by default (ADR 0064): the stored value is read as the store reads it
    $stored = (string) app('session')->driver()->getHandler()->read($id);
    expect(config('session.encrypt') ? (string) app('encrypter')->decrypt($stored) : $stored)->toContain(EndOutdatedSessions::SESSION_KEY);

    svTakeOver('link');
    expect(app('session')->driver()->getHandler()->read($id))->not->toBe('');

    svRequest('fortify', $cookies)->assertUnauthorized();
    test()->assertGuest();
    // The store forgets the session: the same cookie finds nothing any more
    expect(app('session')->driver()->getHandler()->read($id))->toBe('');
});

// ---------------------------------------------------------------------------------------------------------------------
// The value in the session
// ---------------------------------------------------------------------------------------------------------------------

test('a sign-in stores the version of the account in the session, also a sign-in by the remember cookie', function () {
    $account = svSignedUpAccount();
    $account->forceFill(['session_version' => 3])->save();

    $cookies = svSignInWithPassword();
    expect(session(EndOutdatedSessions::SESSION_KEY))->toBe(3);

    // A browser with the remember cookie only: the guard signs it in, and the session gets the version
    svRequest('page', $cookies['remember'])->assertOk();
    test()->assertAuthenticatedAs($account);
    expect(session(EndOutdatedSessions::SESSION_KEY))->toBe(3);
});

test('a session without the value counts as 0: it works for an account of version 0, and ends for another version', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => SV_EMAIL]);

    // actingAs() fires no sign-in: the session has no value
    $this->actingAs($user)->get('/dashboard')->assertOk();
    expect(session()->has(EndOutdatedSessions::SESSION_KEY))->toBeFalse();

    $user->forceFill(['session_version' => 1])->save();
    svNewBrowser();
    $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();

    svNewBrowser();
    $this->actingAs($user->fresh())->getJson('/user/profile')->assertUnauthorized();
    $this->assertGuest();
});

test('a session whose value is the version of the account goes on', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => SV_EMAIL]);
    $user->forceFill(['session_version' => 2])->save();

    $this->actingAs($user->fresh())->withSession([EndOutdatedSessions::SESSION_KEY => 2])->get('/dashboard')->assertOk();
    $this->assertAuthenticatedAs($user);

    svNewBrowser();
    $this->actingAs($user->fresh())->withSession([EndOutdatedSessions::SESSION_KEY => 1])->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a request with an API token has no session: the version of the sessions does not change its answer', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => SV_EMAIL]);
    $user->forceFill(['session_version' => 5])->save();
    $token = $user->createToken('a token', ['*'])->plainTextToken;

    $this->getJson('/api/v1/user', ['Authorization' => 'Bearer '.$token])->assertOk();
});

test('the sign-in of the person who took the account over holds the new version, and goes on', function (string $way) {
    svSessionDriver('array');
    svSignedUpAccount();

    svTakeOver($way);
    if ($way === 'reset') {
        svNewBrowser();
        $this->post('/login', ['email' => SV_EMAIL, 'password' => SV_NEW_PASSWORD])->assertRedirect(route('dashboard'));
    }

    expect(session(EndOutdatedSessions::SESSION_KEY))->toBe(1);
    $this->get('/dashboard')->assertOk();
    $this->getJson('/user/profile')->assertOk();
})->with([
    'a sign-in link' => ['link'],
    'a password reset' => ['reset'],
    'the provider' => ['provider'],
]);

test('the middleware runs in the web group and in the api group, before the sign-in check of the route', function (string $method, string $uri) {
    $groups = app('router')->getMiddlewareGroups();
    expect($groups['web'])->toContain(EndOutdatedSessions::class)
        ->and($groups['api'])->toContain(EndOutdatedSessions::class);

    $route = app('router')->getRoutes()->match(Request::create($uri, $method));
    $middleware = array_values(array_filter(app('router')->gatherRouteMiddleware($route), 'is_string'));
    $ours = array_search(EndOutdatedSessions::class, $middleware, true);
    $authenticate = array_key_first(array_filter($middleware, fn (string $entry) => str_starts_with($entry, Authenticate::class)));
    expect($ours)->toBeInt()
        ->and($authenticate)->toBeInt()
        ->and($ours)->toBeLessThan($authenticate);
})->with([
    'a page' => ['GET', '/user/profile'],
    'a route of Fortify' => ['PUT', '/user/profile-information'],
    'the API' => ['GET', '/api/v1/user'],
]);
