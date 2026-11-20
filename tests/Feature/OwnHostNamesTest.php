<?php

use App\Http\Middleware\AnswerOnlyForOwnHostNames;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/*
 * The application answers only for its own host names: the host of APP_URL, the names of TRUSTED_HOSTS and the loopback
 * names. Another host name gets the answer 400. Every address that the application builds has a host name of the
 * configuration.
 */

const OWN_HOST_NAMES_NO_ADDRESS = 'This instance is not configured. Set APP_URL to the full public address, for example https://app.example.com. See SELF_HOSTING.md.';

/**
 * The answer of the health address for a request whose Host header is exactly the given text. The address of a test
 * request cannot hold every text.
 *
 * @return TestResponse<Response>
 */
function ownHostNamesAnswerFor(string $hostHeader): TestResponse
{
    $request = Request::create('https://app.example.com/up');
    $request->headers->set('Host', $hostHeader);

    return TestResponse::fromBaseResponse(app(Kernel::class)->handle($request));
}

/**
 * A route that answers with the address of the sign-in page, as the application builds it during the request
 */
function ownHostNamesLinkRoute(): void
{
    Route::get('/built-link', static fn (): string => route('login'));
}

/**
 * On a server, each request starts without a list of host names: the middleware sets it. The requests of one test share
 * the list of the request before, so a test that needs the state of a server empties it between two requests.
 */
function ownHostNamesNextRequest(): void
{
    Request::setTrustedHosts([]);
}

beforeEach(function () {
    config(['app.url' => 'https://app.example.com', 'app.trusted_hosts' => '']);
});

test('the application answers for the host of APP_URL', function () {
    $this->get('https://app.example.com/up')->assertOk();
    $this->get('https://app.example.com:8443/up')->assertOk();
});

test('another host name gets the answer 400', function () {
    $this->get('https://other.example/up')->assertStatus(400);
    $this->get('https://other.example/')->assertStatus(400);
    $this->getJson('https://other.example/api/v1/ping')->assertStatus(400);
});

test('a request for another host name does no work: no notification, no database write', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('https://other.example/forgot-password', ['email' => $user->email])->assertStatus(400);

    Notification::assertNothingSent();
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

test('a request for another host name gets no cookie', function () {
    $response = $this->get('https://other.example/')->assertStatus(400);

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->has('Set-Cookie'))->toBeFalse();

    // The same page by the host of APP_URL starts a session
    expect($this->get('https://app.example.com/')->assertOk()->headers->getCookies())->not->toBe([]);
});

test('the log names a refused host name once a minute', function () {
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = [$event->level, $event->message, $event->context];
    });

    $this->get('https://other.example:8444/up?value=1')->assertStatus(400);

    expect($logged->getArrayCopy())->toBe([['warning', AnswerOnlyForOwnHostNames::REFUSED_MESSAGE, ['host' => 'other.example']]])
        ->and(AnswerOnlyForOwnHostNames::REFUSED_MESSAGE)->toBe('A request for a host name that is not in APP_URL or TRUSTED_HOSTS was refused');

    // A second refused request in the same minute logs nothing
    ownHostNamesNextRequest();
    $this->get('https://second.example.org/up')->assertStatus(400);
    ownHostNamesNextRequest();
    $this->post('https://other.example/forgot-password', ['email' => 'member@example.com'])->assertStatus(400);
    expect($logged)->toHaveCount(1);

    // A minute later, the next refused request logs its name
    $this->travel(61)->seconds();
    ownHostNamesNextRequest();
    $this->get('https://second.example.org/up')->assertStatus(400);
    expect($logged)->toHaveCount(2)
        ->and($logged[1])->toBe(['warning', AnswerOnlyForOwnHostNames::REFUSED_MESSAGE, ['host' => 'second.example.org']]);
});

test('the log line holds at most 255 characters of a refused host name', function () {
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = $event->context;
    });

    ownHostNamesAnswerFor(str_repeat('a', 300).'.example')->assertStatus(400);

    expect($logged->getArrayCopy())->toBe([['host' => str_repeat('a', 255)]]);
});

test('a request for a host name of the instance logs nothing', function () {
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = $event->message;
    });

    $this->get('https://app.example.com/up')->assertOk();
    $this->get('http://127.0.0.1:8000/up')->assertOk();

    expect($logged)->toHaveCount(0);
});

test('the reset link uses the host of the instance', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('https://app.example.com/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        expect(parse_url($notification->toMail($user)->actionUrl, PHP_URL_HOST))->toBe('app.example.com');

        return true;
    });
});

test('the application answers for each name of TRUSTED_HOSTS, with spaces and an empty entry in the list', function () {
    config(['app.trusted_hosts' => ' www.example.com , ,second.example.org,']);

    expect(AnswerOnlyForOwnHostNames::names())
        ->toBe(['app.example.com', 'www.example.com', 'second.example.org', 'localhost', '127.0.0.1', '[::1]']);

    $this->get('https://www.example.com/up')->assertOk();
    $this->get('https://second.example.org/up')->assertOk();
    $this->get('https://app.example.com/up')->assertOk();
    $this->get('https://third.example.org/up')->assertStatus(400);
});

test('a name matches as a whole', function (string $host) {
    config(['app.trusted_hosts' => 'www.example.com']);

    $this->get("https://{$host}/up")->assertStatus(400);
})->with([
    'a name that ends with the host of APP_URL' => ['other-app.example.com'],
    'a name that starts with the host of APP_URL' => ['app.example.com.other.example'],
    'a sub-domain of the host of APP_URL' => ['sub.app.example.com'],
    'the parent domain of the host of APP_URL' => ['example.com'],
    'another character in the place of a dot' => ['appXexample.com'],
    'a name that ends with a name of the list' => ['other-www.example.com'],
    'a name that starts with a name of the list' => ['www.example.com.other.example'],
    'a sub-domain of a name of the list' => ['sub.www.example.com'],
    'another character in the place of a dot of the list' => ['wwwXexample.com'],
]);

test('a host name that is not written as a plain name gets the answer 400', function (string $hostHeader) {
    ownHostNamesAnswerFor('app.example.com')->assertOk();

    ownHostNamesAnswerFor($hostHeader)->assertStatus(400);
})->with([
    'a dot at the end' => ['app.example.com.'],
    'another address in brackets' => ['[::2]'],
    'an at sign' => ['app.example.com@other.example'],
    'an at sign before the host of APP_URL' => ['other.example@app.example.com'],
    'a port that is not a number' => ['app.example.com:port'],
    'a port with a letter' => ['app.example.com:80a'],
]);

test('an entry of TRUSTED_HOSTS can be an address', function () {
    config(['app.trusted_hosts' => '10.0.0.5,[2001:db8::1]']);

    $this->get('http://10.0.0.5/up')->assertOk();
    $this->get('http://10.0.0.5:8000/up')->assertOk();
    $this->get('http://[2001:db8::1]/up')->assertOk();
    $this->get('http://[2001:db8::1]:8000/up')->assertOk();
    $this->get('http://10.0.0.6/up')->assertStatus(400);
    $this->get('http://[2001:db8::2]/up')->assertStatus(400);
});

test('an entry of TRUSTED_HOSTS with a scheme or a port matches nothing', function () {
    config(['app.trusted_hosts' => 'https://www.example.com,second.example.org:8443,http://third.example.org:8080']);

    $this->get('https://www.example.com/up')->assertStatus(400);
    $this->get('https://second.example.org/up')->assertStatus(400);
    $this->get('https://second.example.org:8443/up')->assertStatus(400);
    $this->get('http://third.example.org:8080/up')->assertStatus(400);
    $this->get('https://app.example.com/up')->assertOk();
});

test('a star in a name of the list has no special meaning', function () {
    config(['app.trusted_hosts' => '*.example.org,*']);

    $this->get('https://www.example.org/up')->assertStatus(400);
    $this->get('https://other.example/up')->assertStatus(400);
    $this->get('https://app.example.com/up')->assertOk();
});

test('the application answers for each loopback name, with and without a port', function (string $host) {
    $this->get("http://{$host}/up")->assertOk();
})->with([
    'localhost',
    'localhost:8000',
    '127.0.0.1',
    '127.0.0.1:8000',
    '[::1]',
    '[::1]:8000',
]);

test('the letter case of a host name does not count', function () {
    config(['app.trusted_hosts' => 'WWW.Example.com']);

    $this->get('https://APP.EXAMPLE.COM/up')->assertOk();
    $this->get('https://App.Example.Com:8443/up')->assertOk();
    $this->get('https://www.example.com/up')->assertOk();
    $this->get('http://LOCALHOST/up')->assertOk();
});

test('a host name that a trusted proxy gives follows the same rule', function () {
    config(['app.trusted_hosts' => 'www.example.com']);
    TrustProxies::at('*');

    $this->withHeaders(['X-Forwarded-Host' => 'www.example.com'])->get('https://app.example.com/up')->assertOk();
    $this->withHeaders(['X-Forwarded-Host' => 'other.example'])->get('https://app.example.com/up')->assertStatus(400);
});

test('a host name of a proxy that is not trusted is not read', function () {
    $this->withHeaders(['X-Forwarded-Host' => 'other.example'])->get('https://app.example.com/up')->assertOk();
});

test('with a list of trusted proxies, only a proxy of the list gives the host name', function () {
    TrustProxies::at(['10.0.0.1', '10.0.1.0/24']);

    foreach (['10.0.0.1', '10.0.1.7'] as $proxy) {
        $this->withServerVariables(['REMOTE_ADDR' => $proxy])->withHeaders(['X-Forwarded-Host' => 'other.example'])
            ->get('https://app.example.com/up')->assertStatus(400);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->withHeaders(['X-Forwarded-Host' => 'other.example'])
        ->get('https://app.example.com/up')->assertOk();
});

test('the Forwarded header is not read', function () {
    ownHostNamesLinkRoute();
    TrustProxies::at('*');

    $this->withHeaders(['Forwarded' => 'for=192.0.2.1;host=other.example;proto=http'])
        ->get('https://app.example.com/built-link')->assertOk()->assertContent('https://app.example.com/login');
});

// ---------------------------------------------------------------------------------------------------------------------
// The addresses that the application builds
// ---------------------------------------------------------------------------------------------------------------------

test('a link always uses a host name of the configuration', function (string $appUrl, string $address, string $link) {
    config(['app.url' => $appUrl, 'app.trusted_hosts' => 'WWW.Example.com']);
    ownHostNamesLinkRoute();

    $this->get($address.'/built-link')->assertOk()->assertContent($link);
})->with([
    'the host of APP_URL' => ['https://app.example.com', 'https://app.example.com', 'https://app.example.com/login'],
    'the host of APP_URL with another port' => ['https://app.example.com', 'https://app.example.com:8444', 'https://app.example.com/login'],
    'the host of APP_URL with another port, when APP_URL has a port' => ['https://app.example.com:8443', 'https://app.example.com:8444', 'https://app.example.com:8443/login'],
    'the host of APP_URL without the port of APP_URL' => ['https://app.example.com:8443', 'https://app.example.com', 'https://app.example.com:8443/login'],
    'the host of APP_URL in another letter case' => ['https://App.Example.com', 'https://APP.EXAMPLE.COM', 'https://app.example.com/login'],
    'localhost with a port' => ['https://app.example.com', 'http://localhost:8000', 'http://app.example.com/login'],
    '127.0.0.1' => ['https://app.example.com', 'http://127.0.0.1', 'http://app.example.com/login'],
    '[::1] with a port, when APP_URL has a port' => ['https://app.example.com:8443', 'http://[::1]:8000', 'http://app.example.com:8443/login'],
    'a name of TRUSTED_HOSTS' => ['https://app.example.com', 'https://www.example.com', 'https://www.example.com/login'],
    'a name of TRUSTED_HOSTS on the port of APP_URL' => ['https://app.example.com:8443', 'https://www.example.com:8443', 'https://www.example.com:8443/login'],
    'a name of TRUSTED_HOSTS with another port, when APP_URL has a port' => ['https://app.example.com:8443', 'https://www.example.com:8444', 'https://www.example.com:8443/login'],
    'a name of TRUSTED_HOSTS, when APP_URL has the default port of its scheme' => ['https://app.example.com:443', 'https://www.example.com', 'https://www.example.com/login'],
    'APP_URL with the default port of https' => ['https://app.example.com:443', 'https://app.example.com', 'https://app.example.com/login'],
    'APP_URL with the default port of http' => ['http://app.example.com:80', 'http://app.example.com', 'http://app.example.com/login'],
    'APP_URL with the default port of the other scheme' => ['https://app.example.com:80', 'https://app.example.com', 'https://app.example.com:80/login'],
    'the scheme of the request, not the one of APP_URL (http)' => ['https://app.example.com', 'http://app.example.com', 'http://app.example.com/login'],
    'the scheme of the request, not the one of APP_URL (https)' => ['http://app.example.com', 'https://app.example.com', 'https://app.example.com/login'],
    'APP_URL with a path' => ['https://app.example.com/path', 'https://app.example.com', 'https://app.example.com/login'],
    'a loopback name that is the host of APP_URL' => ['http://localhost:4200', 'http://localhost:8000', 'http://localhost:4200/login'],
    'another loopback name than the host of APP_URL' => ['http://localhost:4200', 'http://127.0.0.1:8000', 'http://localhost:4200/login'],
]);

test('a link uses a host name of the configuration when a trusted proxy gives the host name and the port', function () {
    ownHostNamesLinkRoute();
    TrustProxies::at('*');

    $this->withHeaders(['X-Forwarded-Host' => 'app.example.com:8444', 'X-Forwarded-Proto' => 'https'])
        ->get('http://127.0.0.1:8000/built-link')->assertOk()->assertContent('https://app.example.com/login');
    $this->withHeaders(['X-Forwarded-Host' => 'localhost:8444', 'X-Forwarded-Port' => '8444'])
        ->get('https://app.example.com/built-link')->assertOk()->assertContent('https://app.example.com/login');
    $this->withHeaders(['X-Forwarded-Port' => '8444'])
        ->get('https://app.example.com/built-link')->assertOk()->assertContent('https://app.example.com/login');
});

test('a signed link of a request by a configured name is valid in a second request by that name', function (string $appUrl, string $address) {
    config(['app.url' => $appUrl, 'app.trusted_hosts' => 'www.example.com']);
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post($address.'/email/verification-notification')->assertSessionHasNoErrors();

    $link = '';
    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user, &$link) {
        $link = $notification->toMail($user)->actionUrl;

        return true;
    });
    expect($link)->toStartWith($address.'/');

    $this->actingAs($user)->get($link)->assertRedirect();
    expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();
})->with([
    'the host of APP_URL (https)' => ['https://app.example.com', 'https://app.example.com'],
    'the host of APP_URL (http)' => ['http://app.example.com', 'http://app.example.com'],
    'the host of APP_URL with its port' => ['https://app.example.com:8443', 'https://app.example.com:8443'],
    'the host of APP_URL, when APP_URL has the default port of its scheme' => ['https://app.example.com:443', 'https://app.example.com'],
    'a name of TRUSTED_HOSTS' => ['https://app.example.com', 'https://www.example.com'],
    'a name of TRUSTED_HOSTS on the port of APP_URL' => ['https://app.example.com:8443', 'https://www.example.com:8443'],
]);

test('a file address and a redirect use a host name of the configuration', function (string $appUrl, string $address, string $root) {
    config(['app.url' => $appUrl, 'app.trusted_hosts' => 'www.example.com', 'app.asset_url' => null]);
    Route::get('/built-file-address', static fn (): string => asset('images/picture.png'));
    Route::get('/built-redirect', static fn () => redirect('/somewhere'));
    Route::get('/built-route-redirect', static fn () => to_route('login'));

    $this->get($address.'/built-file-address')->assertOk()->assertContent($root.'/images/picture.png');
    $this->get($address.'/built-redirect')->assertRedirect($root.'/somewhere');
    $this->get($address.'/built-route-redirect')->assertRedirect($root.'/login');
})->with([
    'the host of APP_URL' => ['https://app.example.com', 'https://app.example.com', 'https://app.example.com'],
    'the host of APP_URL with another port' => ['https://app.example.com', 'https://app.example.com:8444', 'https://app.example.com'],
    'the host of APP_URL with another port, when APP_URL has a port' => ['https://app.example.com:8443', 'https://app.example.com:8444', 'https://app.example.com:8443'],
    'a loopback name with a port' => ['https://app.example.com', 'http://127.0.0.1:8000', 'http://app.example.com'],
    'a name of TRUSTED_HOSTS with another port' => ['https://app.example.com', 'https://www.example.com:8444', 'https://www.example.com'],
]);

test('the rule is on in production', function () {
    configuredInstance();
    $this->app->detectEnvironment(fn () => 'production');

    $this->get('https://app.example.com/up')->assertOk();
    $this->get('https://other.example/up')->assertStatus(400);
    $this->get('https://other.example/')->assertStatus(400);
});

test('an instance that is not configured answers 503 for every host name', function () {
    config(['legal.operator' => '', 'legal.contact_email' => '']);
    $this->app->detectEnvironment(fn () => 'production');

    $this->get('https://app.example.com/up')->assertStatus(503);
    $this->get('https://other.example/up')->assertStatus(503);
});

test('the local environment accepts every host name', function () {
    $this->app->detectEnvironment(fn () => 'local');

    $this->get('https://other.example/up')->assertOk();
    $this->get('http://192.168.1.20:4200/up')->assertOk();
});

test('in the local environment, a link uses the host name of the request', function () {
    ownHostNamesLinkRoute();
    $this->app->detectEnvironment(fn () => 'local');

    $this->get('http://192.168.1.20:4200/built-link')->assertOk()->assertContent('http://192.168.1.20:4200/login');
});

// ---------------------------------------------------------------------------------------------------------------------
// APP_URL is a full address
// ---------------------------------------------------------------------------------------------------------------------

test('an instance whose APP_URL has no host answers 503 with the message on every address', function (?string $appUrl) {
    config(['app.url' => $appUrl]);
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = $event->level.': '.$event->message;
    });

    foreach (['https://app.example.com/up', 'https://app.example.com/', 'https://app.example.com/login', 'http://127.0.0.1:8000/up', 'http://localhost/'] as $address) {
        $response = $this->get($address)->assertStatus(503)->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        expect($response->getContent())->toBe(OWN_HOST_NAMES_NO_ADDRESS)
            ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    }
    $this->getJson('https://app.example.com/api/v1/ping')->assertStatus(503);

    // The log gets the message once a minute, not once for each request
    expect($logged->getArrayCopy())->toBe(['critical: '.OWN_HOST_NAMES_NO_ADDRESS]);
})->with([
    'an empty value' => [''],
    'no value' => [null],
    'a name without a scheme' => ['app.example.com'],
    'a scheme without a name' => ['https://'],
]);

test('in production, an instance whose APP_URL has no host answers 503 too', function () {
    configuredInstance();
    config(['app.url' => 'app.example.com']);
    $this->app->detectEnvironment(fn () => 'production');

    $response = $this->get('https://app.example.com/up')->assertStatus(503);

    expect($response->getContent())->toBe(OWN_HOST_NAMES_NO_ADDRESS);
});

test('the local environment does not need a full APP_URL', function (string $appUrl) {
    config(['app.url' => $appUrl]);
    $this->app->detectEnvironment(fn () => 'local');

    $this->get('http://192.168.1.20:4200/up')->assertOk();
    $this->get('http://localhost:4200/')->assertOk();
})->with(['', 'app.example.com']);

test('the check stands right after the middleware that trusts the proxies', function () {
    $global = app(Kernel::class)->getGlobalMiddleware();
    $position = array_search(AnswerOnlyForOwnHostNames::class, $global, true);

    expect($position)->not->toBeFalse()
        ->and($global[(int) $position - 1])->toBe(TrustProxies::class);
});
