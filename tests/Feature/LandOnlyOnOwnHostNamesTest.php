<?php

use App\Http\Middleware\LandOnlyOnOwnHostNames;
use App\Mail\MagicLink;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

/*
 * After a sign-in, the application goes only to an address of its own host names (ADR 0068). The session keeps the
 * address to open after the sign-in only when it is an address of the application, for every way to sign in: the form,
 * the answer of the second factor, the sign-in link, the sign-up link and the address check. The callback of the provider
 * is in OpenIdConnectTest.
 */

const OWN_LANDING_OTHER_ADDRESS = 'https://other.example/x';
const OWN_LANDING_INTENDED = 'url.intended';

/**
 * The session gets the address of another host as the address to open after the sign-in, in one of two ways: a request
 * that is not a GET, with that address in its `Referer` header, on a page that needs a sign-in (the framework then keeps
 * the header), or a session that holds the address already
 */
function ownLandingOtherAddress(string $how): void
{
    if ($how === 'a session that holds the address') {
        test()->withSession([OWN_LANDING_INTENDED => OWN_LANDING_OTHER_ADDRESS]);

        return;
    }

    test()->call('HEAD', '/dashboard', server: ['HTTP_REFERER' => OWN_LANDING_OTHER_ADDRESS])->assertRedirect();
    expect(session()->has(OWN_LANDING_INTENDED))->toBeFalse();
}

/**
 * The answer is a redirect to an address of the application, and the session keeps no address of another host
 */
function ownLandingAssertOwn(TestResponse $response, ?string $address = null): void
{
    $response->assertRedirect();
    $location = (string) $response->headers->get('Location');

    expect($location)->toStartWith(URL::to('/'))->not->toContain('other.example');
    if ($address !== null) {
        expect($location)->toBe($address);
    }
    expect(session()->has(OWN_LANDING_INTENDED))->toBeFalse();
}

function ownLandingMember(): User
{
    return User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => 'ada@example.com']);
}

// ---------------------------------------------------------------------------------------------------------------------
// Each way to sign in
// ---------------------------------------------------------------------------------------------------------------------

test('the sign-in form lands on an own address', function (string $how) {
    $user = ownLandingMember();
    ownLandingOtherAddress($how);

    ownLandingAssertOwn($this->post('/login', ['email' => $user->email, 'password' => 'password']), route('dashboard'));
    $this->assertAuthenticatedAs($user);
})->with('how the other address came');

test('the answer of the second factor lands on an own address', function (string $how) {
    $user = ownLandingMember();
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one'])),
        'two_factor_confirmed_at' => now(),
    ])->save();
    ownLandingOtherAddress($how);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $this->assertGuest();

    ownLandingAssertOwn($this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)]), route('dashboard'));
    $this->assertAuthenticatedAs($user);
})->with('how the other address came');

test('the sign-in link lands on an own address', function (string $how) {
    Mail::fake();
    $user = ownLandingMember();
    ownLandingOtherAddress($how);

    $this->post('/login/link', ['email' => $user->email])->assertRedirect(route('login'));
    $token = Mail::queued(MagicLink::class)->last()?->token;
    expect($token)->toBeString();

    $this->get('/login/link/'.$token)->assertOk();
    ownLandingAssertOwn($this->post('/login/link/'.$token), route('dashboard'));
    $this->assertAuthenticatedAs($user);
})->with('how the other address came');

test('the sign-up link lands on an own address', function (string $how) {
    ownLandingOtherAddress($how);

    $response = signUpThroughLink(['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'password' => 'a-Long-Password-9!']);

    ownLandingAssertOwn($response, route('dashboard').'?verified=1');
    $this->assertAuthenticated();
})->with('how the other address came');

test('the address check lands on an own address', function (string $how) {
    $user = User::factory()->unverified()->withUserProfile()->withPersonalCommunity()->create();
    $this->actingAs($user);
    // A signed-in account without a verified address: the page sends it to the notice of the address check
    ownLandingOtherAddress($how);

    $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]);

    ownLandingAssertOwn($this->get($link), route('dashboard').'?verified=1');
    expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();
})->with('how the other address came');

dataset('how the other address came', [
    'a request that is not a GET, with the address in its Referer header',
    'a session that holds the address',
]);

// ---------------------------------------------------------------------------------------------------------------------
// What the session keeps
// ---------------------------------------------------------------------------------------------------------------------

test('a request that is not a GET keeps no address of another host, whatever the page asks for', function (string $method, string $address, string $who) {
    $user = match ($who) {
        'a guest' => null,
        'an account without a verified address' => User::factory()->unverified()->withUserProfile()->withPersonalCommunity()->create(),
        default => ownLandingMember(),
    };
    if ($user !== null) {
        $this->actingAs($user);
    }

    $this->call($method, $address, server: ['HTTP_REFERER' => OWN_LANDING_OTHER_ADDRESS])->assertRedirect();

    expect(session()->has(OWN_LANDING_INTENDED))->toBeFalse();
})->with([
    'a HEAD of a guest: the sign-in' => ['HEAD', '/dashboard', 'a guest'],
    'a POST of a guest: the sign-in' => ['POST', '/user/api-tokens', 'a guest'],
    'a HEAD without a verified address: the address check' => ['HEAD', '/dashboard', 'an account without a verified address'],
    'a POST of a member: the confirmation of the password' => ['POST', '/user/two-factor-authentication', 'a member'],
]);

test('an own address in the Referer header still works', function () {
    $user = ownLandingMember();
    $own = URL::to('/community/harbour-of-landings?page=2');

    $this->call('HEAD', '/dashboard', server: ['HTTP_REFERER' => $own])->assertRedirect(route('login'));
    expect(session(OWN_LANDING_INTENDED))->toBe($own);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($own);
    $this->assertAuthenticatedAs($user);
});

test('the page that a guest asked for still opens after the sign-in', function () {
    $user = ownLandingMember();

    $this->get('/user/profile', ['Referer' => OWN_LANDING_OTHER_ADDRESS])->assertRedirect(route('login'));
    expect(session(OWN_LANDING_INTENDED))->toBe(URL::to('/user/profile'));

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(URL::to('/user/profile'));
});

test('an address of a second host name of the instance is kept', function () {
    config(['app.url' => 'https://app.example.com', 'app.trusted_hosts' => 'www.example.com']);
    $user = ownLandingMember();

    $this->call('HEAD', 'https://app.example.com/dashboard', server: ['HTTP_REFERER' => 'https://www.example.com/community/x'])->assertRedirect();
    expect(session(OWN_LANDING_INTENDED))->toBe('https://www.example.com/community/x');

    $this->post('https://app.example.com/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('https://www.example.com/community/x');

    // A name under an own host name is another name
    $this->withSession([OWN_LANDING_INTENDED => 'https://x.app.example.com/community/x'])->get('https://app.example.com/login');
    expect(session()->has(OWN_LANDING_INTENDED))->toBeFalse();
});

test('the local environment keeps an address of the host name that the developer opened', function () {
    $this->app->detectEnvironment(fn () => 'local');

    $this->call('HEAD', 'http://192.168.1.20:4200/dashboard', server: ['HTTP_REFERER' => 'http://192.168.1.20:4200/community/x'])->assertRedirect();
    expect(session(OWN_LANDING_INTENDED))->toBe('http://192.168.1.20:4200/community/x');

    $this->call('HEAD', 'http://192.168.1.20:4200/dashboard', server: ['HTTP_REFERER' => OWN_LANDING_OTHER_ADDRESS])->assertRedirect();
    expect(session()->has(OWN_LANDING_INTENDED))->toBeFalse();
});

// ---------------------------------------------------------------------------------------------------------------------
// The rule
// ---------------------------------------------------------------------------------------------------------------------

test('an address of the application is a path or an http or https address of an own host name', function (string $address, bool $own) {
    expect(LandOnlyOnOwnHostNames::isOwnAddress($address, ['app.example.com', 'www.example.com', 'localhost', '[::1]']))->toBe($own);
})->with([
    'the own host name' => ['https://app.example.com/community/x?page=2#top', true],
    'the own host name, no path' => ['https://app.example.com', true],
    'the own host name over http' => ['http://app.example.com/x', true],
    'the own host name in another letter case' => ['HTTPS://App.Example.COM/x', true],
    'the own host name with a port' => ['https://app.example.com:8443/x', true],
    'a second host name' => ['https://www.example.com/x', true],
    'a loopback name' => ['http://localhost:4200/dashboard', true],
    'a loopback address of IPv6' => ['http://[::1]:4200/dashboard', true],
    'the own host name without a scheme' => ['//app.example.com/x', true],
    'a path' => ['/dashboard', true],
    'a path with a query' => ['/community/x?page=2', true],
    'the root' => ['/', true],
    'another host' => ['https://other.example/x', false],
    'another host without a scheme' => ['//other.example/x', false],
    'a name under an own host name' => ['https://x.app.example.com/', false],
    'a name that ends with an own host name' => ['https://notapp.example.com/', false],
    'a name that starts with an own host name' => ['https://app.example.com.other.example/', false],
    'an own host name as the user name' => ['https://app.example.com@other.example/', false],
    'a user name before an own host name' => ['https://someone@app.example.com/', false],
    'a user name and a password' => ['https://someone:secret@app.example.com/', false],
    'a backslash before an own host name' => ['https://other.example\\@app.example.com/', false],
    'a backslash in a path' => ['/\\other.example/x', false],
    'three slashes' => ['///other.example/x', false],
    'a scheme with one slash' => ['https:/other.example/x', false],
    'a scheme without a slash' => ['https:other.example', false],
    'another scheme' => ['ftp://app.example.com/x', false],
    'a script' => ['javascript://app.example.com/%0aalert(1)', false],
    'a mail address' => ['mailto:someone@app.example.com', false],
    'a fragment' => ['#top', false],
    'a path without the first slash' => ['dashboard', false],
    'a space' => ['https://app.example.com/ x', false],
    'a tab in the scheme' => ["ht\ttps://app.example.com/", false],
    'a new line' => ["https://app.example.com/\nx", false],
    'a space before' => [' https://app.example.com/', false],
    'an empty text' => ['', false],
]);

test('a value of the session that is not a text is removed', function () {
    $this->withSession([OWN_LANDING_INTENDED => ['https://app.example.com/x']])->get('/login')->assertOk();

    expect(session()->has(OWN_LANDING_INTENDED))->toBeFalse();
});

test('on every route of the pages, the check runs right after the start of the session and before the checks that keep an address', function () {
    $router = app('router');
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn (RoutingRoute $route): bool => in_array('web', $route->gatherMiddleware(), true));
    expect($routes->count())->toBeGreaterThan(50);

    $class = static fn (mixed $middleware): string => is_string($middleware) ? explode(':', $middleware)[0] : '';

    foreach ($routes as $route) {
        $middleware = array_map($class, $router->gatherRouteMiddleware($route));
        $session = array_search(StartSession::class, $middleware, true);
        $check = array_search(LandOnlyOnOwnHostNames::class, $middleware, true);

        // A route without a session keeps no address (the webhooks, the stop link of a mail)
        if ($session === false) {
            continue;
        }

        expect($check)->toBe($session + 1, $route->uri());
        foreach ([Authenticate::class, EnsureEmailIsVerified::class, RequirePassword::class] as $writer) {
            $position = array_search($writer, $middleware, true);
            if ($position !== false) {
                expect($position)->toBeGreaterThan($check, $route->uri().' '.$writer);
            }
        }
    }
});
