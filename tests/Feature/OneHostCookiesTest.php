<?php

use App\Http\Middleware\RefuseUnconfiguredInstance;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/*
 * The cookies belong to one host (ADR 0068). The session cookie belongs to the one host of the instance (`__Host-`
 * prefix, no domain), and so do the cookie `XSRF-TOKEN` and the cookie of "Remember me". In production, the instance
 * answers only with such a session cookie. The API allows requests with cookies from its own origin only.
 */

const ONE_HOST_NO_COOKIE = 'This instance is not configured. Set SESSION_COOKIE to a name that starts with __Host-, for example __Host-knowii_session, and leave SESSION_DOMAIN empty. See SELF_HOSTING.md, section "The cookies belong to one host".';
const ONE_HOST_OTHER_ORIGIN = 'https://other.example';

/**
 * The values of the keys of an example file of the environment, as texts ("KEY=" gives an empty text)
 *
 * @return array<string, string>
 */
function oneHostExampleValues(string $file): array
{
    $values = [];
    foreach (file(base_path($file), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/\A([A-Z0-9_]+)=(.*)\z/', $line, $match) === 1) {
            $values[$match[1]] = $match[2];
        }
    }

    return $values;
}

/**
 * The application runs with the session values of the example file of production, as a new process reads them: the
 * session store, the cookies and the guards are built again
 */
function oneHostProductionValues(): void
{
    $example = oneHostExampleValues('.env.production.example');

    config([
        'session.cookie' => $example['SESSION_COOKIE'],
        'session.domain' => $example['SESSION_DOMAIN'],
        'session.path' => $example['SESSION_PATH'],
        'session.secure' => $example['SESSION_SECURE_COOKIE'] === 'true',
        'session.same_site' => 'lax',
    ]);

    foreach (['session', 'session.store', 'cookie'] as $service) {
        app()->forgetInstance($service);
    }
    app('auth')->forgetGuards();
}

/**
 * The `Set-Cookie` header of the answer for the cookie whose name starts with the given text, in lower case after the
 * value, or null
 */
function oneHostSetCookie(TestResponse $response, string $start): ?string
{
    foreach ($response->headers->all('set-cookie') as $header) {
        if (str_starts_with((string) $header, $start)) {
            [$pair, $attributes] = [...explode(';', (string) $header, 2), ''];

            return $pair.';'.strtolower($attributes);
        }
    }

    return null;
}

/**
 * The attributes of a `Set-Cookie` header, in lower case, without the value of the cookie
 *
 * @return list<string>
 */
function oneHostAttributes(?string $header): array
{
    expect($header)->not->toBeNull();

    $attributes = array_map(trim(...), explode(';', (string) $header));
    array_shift($attributes);

    return array_values(array_filter($attributes, static fn (string $attribute): bool => ! str_starts_with($attribute, 'expires=') && ! str_starts_with($attribute, 'max-age=')));
}

// ---------------------------------------------------------------------------------------------------------------------
// The cookies
// ---------------------------------------------------------------------------------------------------------------------

test('the example files leave the session domain empty, and production names the session cookie with the __Host- prefix', function () {
    $production = oneHostExampleValues('.env.production.example');
    $development = oneHostExampleValues('.env.example');

    expect($production['SESSION_DOMAIN'])->toBe('')
        ->and($production['SESSION_COOKIE'])->toBe('__Host-knowii_session')
        ->and($production['SESSION_SECURE_COOKIE'])->toBe('true')
        ->and($production['SESSION_PATH'])->toBe('/')
        ->and($production)->not->toHaveKey('SESSION_ALLOW_DOMAIN_COOKIE')
        // Plain http cannot use the prefix: the development name has none
        ->and($development['SESSION_DOMAIN'])->toBe('')
        ->and($development['SESSION_COOKIE'])->toBe('knowii_session')
        ->and($development)->not->toHaveKey('SESSION_ALLOW_DOMAIN_COOKIE');
});

test('with the production values, the session cookie and XSRF-TOKEN belong to the one host', function () {
    oneHostProductionValues();

    $response = $this->get('/login')->assertOk();

    // No domain, https only, SameSite=Lax and the path "/" (a __Host- cookie needs it). Only the session cookie is HttpOnly:
    // the front end reads XSRF-TOKEN, which keeps its name
    expect(oneHostAttributes(oneHostSetCookie($response, '__Host-knowii_session=')))->toBe(['path=/', 'secure', 'httponly', 'samesite=lax'])
        ->and(oneHostAttributes(oneHostSetCookie($response, 'XSRF-TOKEN=')))->toBe(['path=/', 'secure', 'samesite=lax']);

    foreach ($response->headers->all('set-cookie') as $header) {
        expect(strtolower((string) $header))->not->toContain('domain=');
    }
});

test('with the production values, the cookie of "Remember me" belongs to the one host', function () {
    oneHostProductionValues();
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])->assertRedirect();
    $this->assertAuthenticatedAs($user);

    expect(oneHostAttributes(oneHostSetCookie($response, 'remember_web_')))->toBe(['path=/', 'secure', 'httponly', 'samesite=lax'])
        ->and(oneHostAttributes(oneHostSetCookie($response, '__Host-knowii_session=')))->toBe(['path=/', 'secure', 'httponly', 'samesite=lax']);

    foreach ($response->headers->all('set-cookie') as $header) {
        expect(strtolower((string) $header))->not->toContain('domain=');
    }
});

// ---------------------------------------------------------------------------------------------------------------------
// The check of the production values
// ---------------------------------------------------------------------------------------------------------------------

test('in production, an instance whose session cookie does not belong to its one host answers 503 on every address', function (?string $name, ?string $domain) {
    configuredInstance();
    config(['session.cookie' => $name, 'session.domain' => $domain]);
    $this->app->detectEnvironment(fn () => 'production');

    foreach (['/up', '/', '/login'] as $address) {
        $response = $this->get($address)->assertStatus(503)->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        expect($response->getContent())->toBe(ONE_HOST_NO_COOKIE)
            ->and($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->headers->all('set-cookie'))->toBe([]);
    }
    $this->getJson('/api/v1/ping')->assertStatus(503);
})->with([
    'a session domain: the host of the instance' => ['__Host-knowii_session', 'app.example.com'],
    'a session domain: the domain above the instance' => ['__Host-knowii_session', 'example.com'],
    'a name with the __Secure- prefix' => ['__Secure-knowii_session', null],
    'a name without a prefix' => ['knowii_session', null],
    'the prefix in another letter case' => ['__host-knowii_session', null],
    'the prefix is not at the start' => ['knowii__Host-session', null],
    'no name' => [null, null],
    'both' => ['__Secure-knowii_session', 'app.example.com'],
]);

test('in production, an instance whose session cookie belongs to its one host answers', function (?string $domain) {
    configuredInstance();
    config(['session.cookie' => '__Host-knowii_session', 'session.domain' => $domain]);
    $this->app->detectEnvironment(fn () => 'production');

    $this->get('/up')->assertOk();
})->with(['no session domain' => [null], 'an empty session domain (SESSION_DOMAIN= in .env)' => ['']]);

test('the message of the check names the two values and the section of the guide', function () {
    expect(RefuseUnconfiguredInstance::NO_ONE_HOST_COOKIE_MESSAGE)->toBe(ONE_HOST_NO_COOKIE)
        ->and((string) file_get_contents(base_path('SELF_HOSTING.md')))->toContain("\n### The cookies belong to one host\n")
        ->toContain('`SESSION_ALLOW_DOMAIN_COOKIE=true`');
});

test('one switch lets an instance share its session cookie with the other hosts of its domain', function () {
    configuredInstance();
    config(['session.cookie' => '__Secure-knowii_session', 'session.domain' => 'example.com', 'session.allow_domain_cookie' => true]);
    $this->app->detectEnvironment(fn () => 'production');

    $this->get('/up')->assertOk();

    // Only the value true is the switch
    config(['session.allow_domain_cookie' => 'true']);
    $this->get('/up')->assertStatus(503);
});

test('the switch is off unless the environment turns it on', function () {
    $load = static function (?string $value): mixed {
        $value === null ? putenv('SESSION_ALLOW_DOMAIN_COOKIE') : putenv("SESSION_ALLOW_DOMAIN_COOKIE={$value}");
        unset($_ENV['SESSION_ALLOW_DOMAIN_COOKIE'], $_SERVER['SESSION_ALLOW_DOMAIN_COOKIE']);

        return (require config_path('session.php'))['allow_domain_cookie'];
    };

    try {
        expect($load(null))->toBeFalse()
            ->and($load(''))->toBeFalse()
            ->and($load('false'))->toBeFalse()
            ->and($load('0'))->toBeFalse()
            ->and($load('no'))->toBeFalse()
            ->and($load('anything'))->toBeFalse()
            ->and($load('true'))->toBeTrue()
            ->and($load('1'))->toBeTrue();
    } finally {
        putenv('SESSION_ALLOW_DOMAIN_COOKIE');
    }

    expect(config('session.allow_domain_cookie'))->toBeFalse();
});

test('outside production, the instance answers with the development name of the session cookie', function (string $environment) {
    config(['session.cookie' => 'knowii_session', 'session.domain' => 'localhost']);
    $this->app->detectEnvironment(fn () => $environment);

    $this->get('/up')->assertOk();
})->with(['testing', 'local']);

// ---------------------------------------------------------------------------------------------------------------------
// The API allows requests with cookies from its own origin only
// ---------------------------------------------------------------------------------------------------------------------

test('the API names no origin that may send cookies', function () {
    expect(config('cors.supports_credentials'))->toBeFalse();

    // The question of a browser before a request of another origin
    $preflight = $this->call('OPTIONS', '/api/v1/user', server: [
        'HTTP_ORIGIN' => ONE_HOST_OTHER_ORIGIN,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization, content-type',
    ]);
    $preflight->assertNoContent();
    expect($preflight->headers->has('Access-Control-Allow-Credentials'))->toBeFalse()
        ->and($preflight->headers->get('Access-Control-Allow-Origin'))->toBe('*')
        ->and($preflight->headers->get('Access-Control-Allow-Headers'))->toBe('authorization, content-type');

    // A simple request of another origin, of a visitor
    foreach (['/api/v1/ping' => 200, '/api/v1/user' => 401] as $address => $status) {
        $response = $this->call('GET', $address, server: ['HTTP_ORIGIN' => ONE_HOST_OTHER_ORIGIN, 'HTTP_ACCEPT' => 'application/json']);

        expect($response->getStatusCode())->toBe($status)
            ->and($response->headers->has('Access-Control-Allow-Credentials'))->toBeFalse()
            ->and($response->headers->get('Access-Control-Allow-Origin'))->toBe('*');
    }
});

test('a token in the Authorization header still works from another origin', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $token = $user->createToken('Script', ['user:read'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/user', ['Origin' => ONE_HOST_OTHER_ORIGIN, 'Referer' => ONE_HOST_OTHER_ORIGIN.'/page'])->assertOk();

    expect($response->headers->has('Access-Control-Allow-Credentials'))->toBeFalse()
        ->and($response->headers->get('Access-Control-Allow-Origin'))->toBe('*');
});

test('a page of the application still calls the API with its session', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $origin = rtrim(config()->string('app.url'), '/');

    $response = $this->actingAs($user)->getJson('/api/v1/user', ['Origin' => $origin, 'Referer' => $origin.'/dashboard'])->assertOk();

    expect($response->headers->has('Access-Control-Allow-Credentials'))->toBeFalse();
});
