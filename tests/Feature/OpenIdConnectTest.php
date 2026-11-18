<?php

use App\Actions\Auth\OpenIdConnectClient;
use App\ApiRoutes;
use App\Contracts\Auth\ReadsOpenIdConnectIdentities;
use App\Contracts\Users\DeletesAccounts;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Models\UserIdentity;
use App\Support\OpenIdConnect;
use App\Support\SafeUrl;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Jetstream\Jetstream;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

/*
 * Sign in with an OpenID Connect provider (#1385, ADR 0045). The provider is fake: HTTP fakes for the discovery document,
 * the keys and the token endpoint, and ID tokens that the test signs with an Ed25519 key made at run time (and one
 * RS256 and one ES256 case).
 */

const OIDC_ISSUER = 'https://auth.example.test';
const OIDC_CLIENT_ID = 'knowii-test-client';
const OIDC_CLIENT_SECRET = 'a-secret-that-is-not-real';
const OIDC_REDIRECT_URI = 'https://knowii.test/auth/oidc/callback';

function oidcB64(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function oidcConfigure(): void
{
    config([
        'app.url' => 'https://knowii.test',
        'services.oidc' => [
            'issuer' => OIDC_ISSUER,
            'client_id' => OIDC_CLIENT_ID,
            'client_secret' => OIDC_CLIENT_SECRET,
            'scopes' => 'openid profile email',
            'label' => 'Example account',
        ],
    ]);
}

/**
 * @return array<string, mixed>
 */
function oidcDiscovery(): array
{
    return [
        'issuer' => OIDC_ISSUER,
        'authorization_endpoint' => OIDC_ISSUER.'/authorize',
        'token_endpoint' => OIDC_ISSUER.'/token',
        'jwks_uri' => OIDC_ISSUER.'/jwks',
        'response_types_supported' => ['code'],
        'code_challenge_methods_supported' => ['S256'],
        'id_token_signing_alg_values_supported' => ['EdDSA'],
        'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
    ];
}

/**
 * An Ed25519 key made now: the secret for JWT::encode and the public key as a JWK
 *
 * @return array{secret: string, public: string, jwk: array<string, string>}
 */
function oidcEdKey(string $kid): array
{
    $pair = sodium_crypto_sign_keypair();
    $public = sodium_crypto_sign_publickey($pair);

    return [
        'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
        'public' => $public,
        'jwk' => ['kty' => 'OKP', 'crv' => 'Ed25519', 'kid' => $kid, 'use' => 'sig', 'alg' => 'EdDSA', 'x' => oidcB64($public)],
    ];
}

/**
 * @return array{pem: string, jwk: array<string, string>}
 */
function oidcRsaKey(string $kid): array
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    $details = openssl_pkey_get_details($key);
    openssl_pkey_export($key, $pem);

    return ['pem' => $pem, 'jwk' => ['kty' => 'RSA', 'kid' => $kid, 'use' => 'sig', 'alg' => 'RS256', 'n' => oidcB64($details['rsa']['n']), 'e' => oidcB64($details['rsa']['e'])]];
}

/**
 * @return array{pem: string, jwk: array<string, string>}
 */
function oidcEcKey(string $kid): array
{
    // OpenSSL drops the leading zero bytes of a coordinate: a JWK holds the full 32 bytes (RFC 7518, 6.2.1.2)
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $details = openssl_pkey_get_details($key);
    openssl_pkey_export($key, $pem);

    return ['pem' => $pem, 'jwk' => ['kty' => 'EC', 'crv' => 'P-256', 'kid' => $kid, 'use' => 'sig', 'alg' => 'ES256', 'x' => oidcB64(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)), 'y' => oidcB64(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT))]];
}

/**
 * The claims of a good ID token for the flow in progress
 *
 * @param  array<string, mixed>  $override
 * @param  list<string>  $without
 * @return array<string, mixed>
 */
function oidcClaims(array $override = [], array $without = []): array
{
    $claims = [
        'iss' => OIDC_ISSUER,
        'aud' => OIDC_CLIENT_ID,
        'sub' => 'sub-ada',
        'iat' => now()->getTimestamp() - 5,
        'exp' => now()->getTimestamp() + 300,
        'nonce' => test()->nonce,
        'email' => 'ada@example.com',
        'email_verified' => true,
        'name' => 'Ada Lovelace',
        ...$override,
    ];

    return array_diff_key($claims, array_flip($without));
}

/**
 * Sign claims with the Ed25519 key of the provider (default), another key, or with RS256 / ES256 keys
 *
 * @param  array<string, mixed>  $claims
 */
function oidcSign(array $claims, ?string $kid = 'ed-1', ?string $secret = null, string $algorithm = 'EdDSA'): string
{
    return JWT::encode($claims, $secret ?? test()->ed['secret'], $algorithm, $kid);
}

/**
 * A token with a header and a signature of our choice (the library refuses to make some of them)
 *
 * @param  array<string, mixed>  $header
 * @param  array<string, mixed>  $claims
 */
function oidcForge(array $header, array $claims, string $signature = ''): string
{
    return oidcB64((string) json_encode($header)).'.'.oidcB64((string) json_encode($claims)).'.'.oidcB64($signature);
}

/**
 * Open the flow as the browser does: the redirect to the provider. Keeps the nonce for the token.
 *
 * @return array<string, string>
 */
function oidcStart(): array
{
    // A guest signs in with the link. A signed-in member connects an account with a POST, after the password was confirmed
    $response = auth()->check()
        ? test()->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])->post('/auth/oidc/connect')
        : test()->get('/auth/oidc/redirect');
    $response->assertRedirect();
    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    test()->nonce = $query['nonce'] ?? null;

    return [...$query, 'location' => $location];
}

/**
 * The provider sends the browser back with a code, and the token endpoint answers with the token
 *
 * @param  array<string, string>  $start
 * @param  array<string, mixed>  $claims
 * @param  array<string, string>  $query
 */
function oidcCallback(array $start, array $claims = [], ?string $token = null, array $query = []): TestResponse
{
    test()->idToken = $token ?? oidcSign(oidcClaims($claims));

    return test()->get('/auth/oidc/callback?'.http_build_query(['code' => 'auth-code', 'state' => $start['state'] ?? '', ...$query]));
}

/**
 * The reasons that the log holds (never the text of a token)
 *
 * @return list<string>
 */
function oidcLoggedReasons(): array
{
    $reasons = [];
    foreach (test()->logged as $entry) {
        if (isset($entry['context']['reason'])) {
            $reasons[] = $entry['context']['reason'];
        }
    }

    return $reasons;
}

/**
 * The sign-in was refused: back on the sign-in page with a plain message, nobody signed in, no account created
 */
function oidcAssertRefused(TestResponse $response, string $reason, int $users = 0): void
{
    $response->assertRedirect(route('login'))->assertSessionHas('oidc_error');
    test()->assertGuest();
    expect(oidcLoggedReasons())->toContain($reason)
        ->and(User::query()->count())->toBe($users)
        ->and(UserIdentity::query()->count())->toBe(0);
}

/**
 * @return array{0: User, 1: string}
 */
function oidcTwoFactorUser(): array
{
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => 'ada@example.com']);
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user, $secret];
}

function oidcMember(string $email = 'ada@example.com'): User
{
    return User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => $email]);
}

beforeEach(function () {
    oidcConfigure();
    $this->ed = oidcEdKey('ed-1');
    $this->jwks = [$this->ed['jwk']];
    $this->discovery = oidcDiscovery();
    $this->idToken = '';
    $this->nonce = null;

    $logged = new ArrayObject;
    $this->logged = $logged;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = ['message' => $event->message, 'context' => $event->context];
    });

    $this->discoveryStatus = 200;
    $this->discoveryHeaders = [];

    // Anything else that the client calls is a mistake
    Http::preventStrayRequests();
    Http::fake([
        'auth.example.test/.well-known/openid-configuration' => fn () => Http::response($this->discovery, $this->discoveryStatus, $this->discoveryHeaders),
        'auth.example.test/jwks' => fn () => Http::response(['keys' => $this->jwks]),
        'auth.example.test/token' => fn () => Http::response(['access_token' => 'AT-SECRET', 'refresh_token' => 'RT-SECRET', 'token_type' => 'Bearer', 'id_token' => $this->idToken]),
    ]);
});

// ---------------------------------------------------------------------------------------------------------------------
// The feature is off, or on
// ---------------------------------------------------------------------------------------------------------------------

test('without the configuration the routes answer 404, the pages have no button and the instance works as before', function (array $config) {
    config(['services.oidc' => $config]);

    $this->get('/auth/oidc/redirect')->assertNotFound();
    $this->get('/auth/oidc/callback?code=x&state=y')->assertNotFound();
    $this->get('/login?provider=1')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Login')->where('oidc', null));
    $this->get('/register')->assertInertia(fn ($page) => $page->where('oidc', null));
    $this->getJson('/api/v1/ping')->assertOk()->assertJsonPath('data.oidc', null);

    $user = User::factory()->withUserProfile()->create();
    $this->actingAs($user->fresh())->get('/user/profile')->assertInertia(fn ($page) => $page->missing('connectedAccounts')->missing('hasPassword'));
    Http::assertNothingSent();
})->with([
    'nothing' => [[]],
    'no issuer' => [['client_id' => OIDC_CLIENT_ID, 'client_secret' => OIDC_CLIENT_SECRET]],
    'no client id' => [['issuer' => OIDC_ISSUER, 'client_secret' => OIDC_CLIENT_SECRET]],
    'no client secret' => [['issuer' => OIDC_ISSUER, 'client_id' => OIDC_CLIENT_ID]],
    'empty values' => [['issuer' => '', 'client_id' => ' ', 'client_secret' => '']],
    'an issuer over http' => [['issuer' => 'http://auth.example.test', 'client_id' => OIDC_CLIENT_ID, 'client_secret' => OIDC_CLIENT_SECRET]],
    'a loopback issuer outside of the local environment' => [['issuer' => 'http://localhost:9000', 'client_id' => OIDC_CLIENT_ID, 'client_secret' => OIDC_CLIENT_SECRET]],
    'an issuer with a query' => [['issuer' => OIDC_ISSUER.'?x=1', 'client_id' => OIDC_CLIENT_ID, 'client_secret' => OIDC_CLIENT_SECRET]],
]);

test('with the configuration the sign-in and registration pages get the provider, and the other ways stay', function () {
    $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Auth/Login')
        ->where('oidc.label', 'Example account')
        ->where('oidc.error', null)
        ->where('canResetPassword', true));
    $this->get('/register')->assertInertia(fn ($page) => $page->component('Auth/Register')->where('oidc.label', 'Example account'));
    $this->getJson('/api/v1/ping')->assertOk()->assertJsonPath('data.oidc.label', 'Example account');

    // The password sign-in still works
    $user = User::factory()->create();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
});

test('the label is the configured text, else the host name of the issuer, cleaned and short', function () {
    expect(OpenIdConnect::label())->toBe('Example account');

    config(['services.oidc.label' => null]);
    expect(OpenIdConnect::label())->toBe('auth.example.test');

    config(['services.oidc.label' => "  \x00\x07 "]);
    expect(OpenIdConnect::label())->toBe('auth.example.test');

    config(['services.oidc.label' => "Our\x07account".str_repeat('x', 100)]);
    expect(OpenIdConnect::label())->toHaveLength(40)->and(OpenIdConnect::label())->toStartWith('Our account');
});

test('the scopes always hold openid and never offline_access', function () {
    config(['services.oidc.scopes' => 'email offline_access profile,email']);
    expect(OpenIdConnect::scopes())->toBe(['openid', 'email', 'profile']);

    config(['services.oidc.scopes' => null]);
    expect(OpenIdConnect::scopes())->toBe(['openid']);
});

test('?provider=1 on the sign-in page goes to the provider at once, without it the page shows', function () {
    $this->get('/login?provider=1')->assertRedirect('/auth/oidc/redirect');
    $this->get('/login')->assertOk();
});

test('a loopback issuer over http is allowed in the local environment only', function () {
    app()->detectEnvironment(fn () => 'local');
    config(['services.oidc.issuer' => 'http://localhost:9000']);
    Http::fake(['localhost:9000/.well-known/openid-configuration' => Http::response([
        ...oidcDiscovery(),
        'issuer' => 'http://localhost:9000',
        'authorization_endpoint' => 'http://localhost:9000/authorize',
        'token_endpoint' => 'http://localhost:9000/token',
        'jwks_uri' => 'http://localhost:9000/jwks',
    ])]);

    expect(OpenIdConnect::enabled())->toBeTrue();
    $this->get('/auth/oidc/redirect')->assertRedirectContains('http://localhost:9000/authorize?');
});

// ---------------------------------------------------------------------------------------------------------------------
// The redirect
// ---------------------------------------------------------------------------------------------------------------------

test('the redirect holds the state, the nonce, the PKCE challenge (S256), the scopes and the registered address', function () {
    $start = oidcStart();

    expect($start['location'])->toStartWith(OIDC_ISSUER.'/authorize?')
        ->and($start['response_type'])->toBe('code')
        ->and($start['client_id'])->toBe(OIDC_CLIENT_ID)
        ->and($start['redirect_uri'])->toBe(OIDC_REDIRECT_URI)
        ->and($start['scope'])->toBe('openid profile email')
        ->and($start['code_challenge_method'])->toBe('S256')
        ->and($start['code_challenge'])->toHaveLength(43)
        ->and(strlen($start['state']))->toBeGreaterThanOrEqual(32)
        ->and(strlen($start['nonce']))->toBeGreaterThanOrEqual(32)
        ->and($start['state'])->not->toBe($start['nonce']);

    // The three secrets of the flow are kept in the session, not in the address (except what the provider needs)
    $flow = session('oidc.flow');
    expect($flow['state'])->toBe($start['state'])
        ->and($flow['nonce'])->toBe($start['nonce'])
        ->and(oidcB64(hash('sha256', $flow['verifier'], true)))->toBe($start['code_challenge'])
        ->and($start['location'])->not->toContain($flow['verifier']);
});

test('each start makes new secrets', function () {
    $first = oidcStart();
    $second = oidcStart();

    expect($second['state'])->not->toBe($first['state'])
        ->and($second['nonce'])->not->toBe($first['nonce'])
        ->and($second['code_challenge'])->not->toBe($first['code_challenge']);
});

test('the scope offline_access is never asked', function () {
    config(['services.oidc.scopes' => 'openid email offline_access']);

    expect(oidcStart()['scope'])->toBe('openid email');
});

test('a provider that cannot be reached gives a plain message and keeps no flow', function () {
    $this->discoveryStatus = 503;

    $this->get('/auth/oidc/redirect')->assertRedirect(route('login'))->assertSessionHas('oidc_error')->assertSessionMissing('oidc.flow');
    expect(oidcLoggedReasons())->toContain('provider_error');
});

test('a discovery document of another issuer, without S256, with an http endpoint or without a usable client authentication is refused', function (array $change) {
    $this->discovery = [...oidcDiscovery(), ...$change];

    $this->get('/auth/oidc/redirect')->assertRedirect(route('login'))->assertSessionHas('oidc_error')->assertSessionMissing('oidc.flow');
    expect(oidcLoggedReasons())->toContain('discovery_refused');
})->with([
    'another issuer' => [['issuer' => 'https://evil.example.test']],
    'no S256' => [['code_challenge_methods_supported' => ['plain']]],
    'no code response type' => [['response_types_supported' => ['token']]],
    'an http token endpoint' => [['token_endpoint' => 'http://auth.example.test/token']],
    'an http keys address' => [['jwks_uri' => 'http://auth.example.test/jwks']],
    'no authorization endpoint' => [['authorization_endpoint' => null]],
    'no client authentication that we support' => [['token_endpoint_auth_methods_supported' => ['private_key_jwt']]],
    'no signature algorithm that we support' => [['id_token_signing_alg_values_supported' => ['HS256']]],
]);

test('the discovery document is read once an hour, the keys too', function () {
    oidcCallback(oidcStart())->assertRedirect(route('dashboard'));
    $this->post('/logout');
    oidcCallback(oidcStart())->assertRedirect(route('dashboard'));

    $count = fn (string $path) => Http::recorded(fn (Request $request) => str_ends_with($request->url(), $path))->count();
    expect($count('/.well-known/openid-configuration'))->toBe(1)
        ->and($count('/jwks'))->toBe(1)
        ->and($count('/token'))->toBe(2);

    $this->post('/logout');
    $this->travel(61)->minutes();
    oidcCallback(oidcStart())->assertRedirect(route('dashboard'));
    expect($count('/.well-known/openid-configuration'))->toBe(2)->and($count('/jwks'))->toBe(2);
});

test('the client only talks to public https addresses: an endpoint on a private address is never called', function () {
    $this->discovery = [...oidcDiscovery(), 'token_endpoint' => 'https://tokens.internal.test/token'];
    SafeUrl::resolveUsing(fn (string $host) => $host === 'tokens.internal.test' ? ['10.0.0.5'] : ['93.184.215.14']);

    $start = oidcStart();
    oidcAssertRefused(oidcCallback($start), 'address_refused');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'tokens.internal.test'));
});

test('a redirect of the provider is not followed', function () {
    $this->discoveryStatus = 302;
    $this->discoveryHeaders = ['Location' => 'http://169.254.169.254/'];

    $this->get('/auth/oidc/redirect')->assertRedirect(route('login'))->assertSessionHas('oidc_error');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '169.254'));
});

// ---------------------------------------------------------------------------------------------------------------------
// The callback: the checks
// ---------------------------------------------------------------------------------------------------------------------

test('the code is exchanged with PKCE and the client secret, and the access and refresh tokens are ignored', function () {
    $start = oidcStart();
    oidcCallback($start)->assertRedirect(route('dashboard'));

    Http::assertSent(function (Request $request) use ($start) {
        if (! str_ends_with($request->url(), '/token')) {
            return false;
        }
        $data = $request->data();

        return $request->method() === 'POST'
            && $request->header('Authorization') === ['Basic '.base64_encode(OIDC_CLIENT_ID.':'.OIDC_CLIENT_SECRET)]
            && $data['grant_type'] === 'authorization_code'
            && $data['code'] === 'auth-code'
            && $data['redirect_uri'] === OIDC_REDIRECT_URI
            && oidcB64(hash('sha256', $data['code_verifier'], true)) === $start['code_challenge']
            && ! isset($data['client_secret']);
    });
});

test('with only client_secret_post the secret goes in the body, with no Authorization header', function () {
    $this->discovery = [...oidcDiscovery(), 'token_endpoint_auth_methods_supported' => ['client_secret_post']];

    oidcCallback(oidcStart())->assertRedirect(route('dashboard'));

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/token')
        && $request->data()['client_id'] === OIDC_CLIENT_ID
        && $request->data()['client_secret'] === OIDC_CLIENT_SECRET
        && ! $request->hasHeader('Authorization'));
});

test('a callback without a flow in the session is refused (and no request goes to the provider)', function () {
    $this->get('/auth/oidc/callback?code=auth-code&state=whatever')->assertRedirect(route('login'))->assertSessionHas('oidc_error');

    $this->assertGuest();
    expect(oidcLoggedReasons())->toContain('no_flow');
    Http::assertNothingSent();
});

test('a callback with a missing, wrong or non-text state is refused', function (?string $state) {
    $start = oidcStart();

    $query = $state === null ? [] : ['state' => $state];
    $this->idToken = oidcSign(oidcClaims());
    $this->get('/auth/oidc/callback?'.http_build_query(['code' => 'auth-code', ...$query]))->assertRedirect(route('login'))->assertSessionHas('oidc_error');

    $this->assertGuest();
    expect(oidcLoggedReasons())->toContain('state_mismatch')->and(User::query()->count())->toBe(0);
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/token'));
})->with([
    'missing' => [null],
    'wrong' => ['not-the-state'],
    'empty' => [''],
]);

test('a state that is an array is refused', function () {
    oidcStart();

    $this->get('/auth/oidc/callback?code=auth-code&state[]=x')->assertRedirect(route('login'))->assertSessionHas('oidc_error');
    expect(oidcLoggedReasons())->toContain('state_mismatch');
});

test('a flow is used once: the same callback twice signs in once', function () {
    $start = oidcStart();
    $url = '/auth/oidc/callback?'.http_build_query(['code' => 'auth-code', 'state' => $start['state']]);
    $this->idToken = oidcSign(oidcClaims());

    $this->get($url)->assertRedirect(route('dashboard'));
    $this->post('/logout');
    $this->get($url)->assertRedirect(route('login'))->assertSessionHas('oidc_error');

    $this->assertGuest();
    expect(User::query()->count())->toBe(1)->and(oidcLoggedReasons())->toContain('no_flow');
});

test('a failure also uses up the flow', function () {
    $start = oidcStart();
    oidcCallback($start, ['nonce' => 'another-nonce']);

    $this->get('/auth/oidc/callback?'.http_build_query(['code' => 'auth-code', 'state' => $start['state']]))->assertRedirect(route('login'));
    expect(oidcLoggedReasons())->toContain('no_flow');
});

test('a flow older than ten minutes is refused', function () {
    $start = oidcStart();
    $this->travel(11)->minutes();

    oidcAssertRefused(oidcCallback($start), 'flow_expired');
});

test('an error of the provider is refused, and its text is never shown', function () {
    $start = oidcStart();

    $response = $this->get('/auth/oidc/callback?'.http_build_query([
        'state' => $start['state'], 'error' => 'access_denied', 'error_description' => 'SECRET-TEXT-OF-THE-PROVIDER',
    ]));

    oidcAssertRefused($response, 'provider_refused');
    expect(session('oidc_error'))->not->toContain('SECRET')->and(session('oidc_error'))->not->toContain('access_denied');
    expect(json_encode($this->logged->getArrayCopy()))->not->toContain('SECRET-TEXT');
});

test('an iss parameter that names another issuer is refused (RFC 9207)', function () {
    $start = oidcStart();

    oidcAssertRefused(oidcCallback($start, [], null, ['iss' => 'https://evil.example.test']), 'provider_refused');
});

test('a callback without a code is refused', function () {
    $start = oidcStart();

    $this->get('/auth/oidc/callback?'.http_build_query(['state' => $start['state']]))->assertRedirect(route('login'));
    expect(oidcLoggedReasons())->toContain('no_code');
});

test('an ID token with a wrong nonce, audience, issuer, or authorized party is refused', function (array $claims, string $reason) {
    oidcAssertRefused(oidcCallback(oidcStart(), $claims), $reason);
})->with([
    'wrong nonce' => [['nonce' => 'another-nonce'], 'wrong_nonce'],
    'no nonce' => [['nonce' => null], 'wrong_nonce'],
    'wrong audience' => [['aud' => 'another-client'], 'wrong_audience'],
    'audiences without ours' => [['aud' => ['a', 'b']], 'wrong_audience'],
    'several audiences and no azp' => [['aud' => [OIDC_CLIENT_ID, 'other']], 'wrong_authorized_party'],
    'an azp that is not ours' => [['azp' => 'other'], 'wrong_authorized_party'],
    'wrong issuer' => [['iss' => 'https://evil.example.test'], 'wrong_issuer'],
    'the issuer with a trailing slash' => [['iss' => OIDC_ISSUER.'/'], 'wrong_issuer'],
    'no subject' => [['sub' => ''], 'bad_subject'],
    'a subject that is not text' => [['sub' => 12], 'bad_subject'],
]);

test('an ID token for several audiences that names us as the authorized party is accepted', function () {
    oidcCallback(oidcStart(), ['aud' => [OIDC_CLIENT_ID, 'other'], 'azp' => OIDC_CLIENT_ID])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});

test('an expired ID token, one without an expiry, and one issued in the future are refused', function (string $case, string $reason) {
    $now = now()->getTimestamp();
    [$claims, $without] = match ($case) {
        'expired' => [['exp' => $now - 10], []],
        'expiring now' => [['exp' => $now], []],
        'no expiry' => [[], ['exp']],
        'issued in the future' => [['iat' => $now + 600, 'exp' => $now + 900], []],
        'no date of issue' => [[], ['iat']],
    };
    $start = oidcStart();

    oidcAssertRefused(oidcCallback($start, $claims, oidcSign(oidcClaims($claims, $without))), $reason);
})->with([
    'expired' => ['expired', 'expired'],
    'expiring now' => ['expiring now', 'expired'],
    'no expiry' => ['no expiry', 'no_expiry'],
    'issued in the future' => ['issued in the future', 'not_yet_valid'],
    'no date of issue' => ['no date of issue', 'bad_issued_at'],
]);

test('an ID token issued a few seconds ahead of our clock is accepted (small skew)', function () {
    oidcCallback(oidcStart(), ['iat' => now()->getTimestamp() + 20])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});

test('an ID token signed by another key with the same key id is refused', function () {
    $other = oidcEdKey('ed-1');
    $start = oidcStart();

    oidcAssertRefused(oidcCallback($start, [], oidcSign(oidcClaims(), 'ed-1', $other['secret'])), 'bad_signature');
});

test('an ID token with a modified payload is refused', function () {
    $start = oidcStart();
    [$header, , $signature] = explode('.', oidcSign(oidcClaims()));
    $forged = oidcB64((string) json_encode(oidcClaims(['email' => 'victim@example.com', 'sub' => 'sub-victim'])));

    oidcAssertRefused(oidcCallback($start, [], $header.'.'.$forged.'.'.$signature), 'bad_signature');
});

test('an ID token with alg none is refused, before any key is read', function () {
    $start = oidcStart();
    $none = oidcForge(['alg' => 'none', 'typ' => 'JWT', 'kid' => 'ed-1'], oidcClaims());

    oidcAssertRefused(oidcCallback($start, [], $none), 'unsupported_algorithm');
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/jwks'));
});

test('an ID token with an HMAC algorithm is refused, even when it is signed with the public key as the secret', function (string $secretKind) {
    // The attack: the public key (known to everyone) used as the secret of an HMAC
    $public = $this->ed['public'];
    $secret = ['raw' => $public, 'base64url' => $this->ed['jwk']['x'], 'base64' => base64_encode($public)][$secretKind];
    // A provider that (wrongly) publishes a shared secret next to its keys: it is never used either
    $this->jwks = [['kty' => 'oct', 'kid' => 'ed-1', 'k' => oidcB64($secret), 'use' => 'sig'], $this->ed['jwk']];

    $start = oidcStart();
    $claims = oidcClaims();
    $header = ['alg' => 'HS256', 'typ' => 'JWT', 'kid' => 'ed-1'];
    $signature = hash_hmac('sha256', oidcB64((string) json_encode($header)).'.'.oidcB64((string) json_encode($claims)), $secret, true);

    oidcAssertRefused(oidcCallback($start, [], oidcForge($header, $claims, $signature)), 'unsupported_algorithm');
})->with(['raw' => 'raw', 'base64url' => 'base64url', 'base64' => 'base64']);

test('the other HMAC algorithms and other unknown algorithms are refused too', function (string $algorithm) {
    $start = oidcStart();

    oidcAssertRefused(oidcCallback($start, [], oidcForge(['alg' => $algorithm, 'kid' => 'ed-1'], oidcClaims(), 'x')), 'unsupported_algorithm');
})->with(['HS384', 'HS512', 'PS256', 'RS512', 'ES384', 'none', 'NONE', '']);

test('a key of the wrong type for the algorithm is not used (an RSA token against an Ed25519 key id)', function () {
    $rsa = oidcRsaKey('ed-1');
    $start = oidcStart();

    // The header says RS256 and names the key id of the Ed25519 key: no RSA key has this id
    oidcAssertRefused(oidcCallback($start, [], oidcSign(oidcClaims(), 'ed-1', $rsa['pem'], 'RS256')), 'unknown_key');
});

test('an ID token signed with RS256 is accepted when the key of the provider is an RSA key', function () {
    $rsa = oidcRsaKey('rsa-1');
    $this->jwks = [$this->ed['jwk'], $rsa['jwk']];
    $start = oidcStart();

    oidcCallback($start, [], oidcSign(oidcClaims(), 'rsa-1', $rsa['pem'], 'RS256'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});

test('an ID token signed with ES256 is accepted', function () {
    $ec = oidcEcKey('ec-1');
    $this->jwks = [$ec['jwk']];
    $start = oidcStart();

    oidcCallback($start, [], oidcSign(oidcClaims(), 'ec-1', $ec['pem'], 'ES256'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});

test('a key id that the keys do not hold: the keys are read again once, then the token is refused', function () {
    // The first sign-in fills the cache
    oidcCallback(oidcStart())->assertRedirect(route('dashboard'));
    $this->post('/logout');
    $ghost = oidcEdKey('ghost');

    $start = oidcStart();
    oidcCallback($start, [], oidcSign(oidcClaims(), 'ghost', $ghost['secret']))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(oidcLoggedReasons())->toContain('unknown_key')
        ->and(Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/jwks'))->count())->toBe(2);
});

test('with no key in the cache the keys are read once, not twice, for an unknown key id', function () {
    $ghost = oidcEdKey('ghost');
    $start = oidcStart();

    oidcAssertRefused(oidcCallback($start, [], oidcSign(oidcClaims(), 'ghost', $ghost['secret'])), 'unknown_key');
    expect(Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/jwks'))->count())->toBe(1);
});

test('a provider that rotated its keys: the new key id is found after one more read', function () {
    oidcCallback(oidcStart())->assertRedirect(route('dashboard'));
    $this->post('/logout');

    $new = oidcEdKey('ed-2');
    $this->jwks = [$new['jwk'], $this->ed['jwk']];
    $start = oidcStart();
    oidcCallback($start, [], oidcSign(oidcClaims(), 'ed-2', $new['secret']))->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    expect(Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/jwks'))->count())->toBe(2);
});

test('a token with no key id is accepted only when exactly one key fits', function () {
    $this->jwks = [$this->ed['jwk']];
    oidcCallback(oidcStart(), [], oidcSign(oidcClaims(), null))->assertRedirect(route('dashboard'));
    $this->post('/logout');
    Cache::flush();

    $second = oidcEdKey('ed-2');
    $this->jwks = [$this->ed['jwk'], $second['jwk']];
    $start = oidcStart();
    oidcCallback($start, [], oidcSign(oidcClaims(), null))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(oidcLoggedReasons())->toContain('unknown_key')->and(User::query()->count())->toBe(1);
});

test('a key that is a private key, a shared secret, an encryption key or too small is dropped', function () {
    $rsa = oidcRsaKey('rsa-1');
    $encryption = [...$this->ed['jwk'], 'kid' => 'enc', 'use' => 'enc'];
    $small = ['kty' => 'RSA', 'kid' => 'small', 'n' => oidcB64(str_repeat("\x9a", 128)), 'e' => 'AQAB'];
    $this->jwks = [$encryption, $small, ['kty' => 'oct', 'kid' => 'hmac', 'k' => 'c2VjcmV0'], $rsa['jwk']];

    $start = oidcStart();
    // Only the RSA key of 2048 bits is usable: a token for an Ed25519 key finds none
    oidcAssertRefused(oidcCallback($start, [], oidcSign(oidcClaims(), 'enc')), 'unknown_key');
    expect(Cache::get('oidc:jwks:'.hash('sha256', OIDC_ISSUER.'/jwks')))->toHaveCount(1);
});

test('email_verified must be exactly true: false, missing, and values that only look true are refused with a message to verify the address', function (array $claims, array $without) {
    $response = oidcCallback(oidcStart(), $claims, oidcSign(oidcClaims($claims, $without)));

    oidcAssertRefused($response, 'email_not_verified');
    expect(session('oidc_error'))->toContain('verif');
})->with([
    'false' => [['email_verified' => false], []],
    'missing' => [[], ['email_verified']],
    'the text true' => [['email_verified' => 'true'], []],
    'one' => [['email_verified' => 1], []],
    'null' => [['email_verified' => null], []],
]);

test('an address that is missing or not an address is refused', function (array $claims, array $without) {
    oidcAssertRefused(oidcCallback(oidcStart(), $claims, oidcSign(oidcClaims($claims, $without))), 'no_email');
})->with([
    'missing' => [[], ['email']],
    'not an address' => [['email' => 'not an address'], []],
    'too long' => [['email' => str_repeat('a', 250).'@example.com'], []],
]);

// ---------------------------------------------------------------------------------------------------------------------
// The callback: who is signed in (the cases of the issue)
// ---------------------------------------------------------------------------------------------------------------------

test('case 1: an identity with this issuer and subject signs in its account, whatever the provider says about the address', function () {
    $user = oidcMember('ada@example.com');
    UserIdentity::factory()->create(['user_id' => $user->id, 'issuer' => OIDC_ISSUER, 'subject' => 'sub-ada']);
    $this->travel(2)->days();

    $response = oidcCallback(oidcStart(), ['email' => 'new-address@example.org', 'email_verified' => false]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect(User::query()->count())->toBe(1)
        // The address of the platform account never follows the provider
        ->and($user->fresh()->email)->toBe('ada@example.com')
        ->and($user->identities()->first()->last_used_at->isToday())->toBeTrue()
        ->and($user->identities()->count())->toBe(1);
});

test('case 3: an account with this address that verified it is connected and signed in (the address is compared without case)', function () {
    $user = oidcMember('Ada@Example.COM');

    oidcCallback(oidcStart())->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    $identity = $user->identities()->firstOrFail();
    expect(User::query()->count())->toBe(1)
        ->and($identity->issuer)->toBe(OIDC_ISSUER)
        ->and($identity->subject)->toBe('sub-ada')
        // Nothing else changes: the password still works
        ->and(Hash::check('password', (string) $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->email)->toBe('Ada@Example.COM');
});

test('case 4: an account that never verified its address is taken over: connected, verified, no password, no other session, no token', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->unverified()->create(['email' => 'ada@example.com']);
    $user->createToken('the token of the creator', ['*']);
    DB::table('sessions')->insert(['id' => 'session-of-the-creator', 'user_id' => $user->id, 'ip_address' => '203.0.113.7', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => now()->getTimestamp()]);
    DB::table('password_reset_tokens')->insert(['email' => 'ada@example.com', 'token' => 'x', 'created_at' => now()]);
    $user->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_recovery_codes' => encrypt('[]'), 'two_factor_confirmed_at' => now(), 'remember_token' => 'old-remember-token'])->save();

    oidcCallback(oidcStart())->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    $fresh = $user->fresh();
    expect($fresh->hasVerifiedEmail())->toBeTrue()
        ->and($fresh->password)->toBeNull()
        ->and($fresh->hasPassword())->toBeFalse()
        ->and($fresh->two_factor_secret)->toBeNull()
        ->and($fresh->two_factor_confirmed_at)->toBeNull()
        ->and($fresh->remember_token)->not->toBe('old-remember-token')
        ->and($user->identities()->count())->toBe(1)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', 'ada@example.com')->count())->toBe(0)
        ->and($user->tokens()->count())->toBe(0)
        ->and(User::query()->count())->toBe(1);
});

test('case 4: the old password no longer works', function () {
    $user = User::factory()->withUserProfile()->unverified()->create(['email' => 'ada@example.com']);
    oidcCallback(oidcStart())->assertRedirect(route('dashboard'));
    $this->post('/logout');

    $this->post('/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertSessionHasErrors();
    $this->post('/login', ['email' => 'ada@example.com', 'password' => ''])->assertSessionHasErrors();
    $this->assertGuest();
    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertUnauthorized();
    expect($user->fresh()->password)->toBeNull();
});

test('case 6: an address that no account has creates an account through the normal creation path', function () {
    oidcCallback(oidcStart())->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Ada Lovelace')
        ->and($user->username)->not->toBe('')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->password)->toBeNull()
        // The profile and the personal community of every new account
        ->and($user->profile()->first()->email)->toBe('ada@example.com')
        ->and($user->profile()->first()->username)->toBe($user->username)
        ->and($user->ownedCommunities()->count())->toBe(1)
        ->and($user->identities()->count())->toBe(1)
        ->and($user->identities()->first()->linked_at)->not->toBeNull();
});

test('case 6: without a name the part of the address before the @ is the name, and the username is free', function () {
    User::factory()->create(['username' => 'ada-taken']);

    oidcCallback(oidcStart(), [], oidcSign(oidcClaims(['email' => 'grace.hopper@example.com'], ['name'])))->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'grace.hopper@example.com')->firstOrFail();
    expect($user->name)->toBe('grace.hopper')->and(User::query()->where('username', $user->username)->count())->toBe(1);
});

test('case 6: a name that is too long or has control characters is cleaned', function () {
    oidcCallback(oidcStart(), ['name' => "Ada\x07".str_repeat('x', 400)])->assertRedirect(route('dashboard'));

    $user = User::query()->firstOrFail();
    expect(mb_strlen($user->name))->toBe(100)->and($user->name)->toStartWith('Ada x');
});

test('the account that the provider created has no password: nothing signs in with a password', function () {
    oidcCallback(oidcStart());
    $this->post('/logout');

    $this->post('/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();
    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => ''])->assertStatus(400);
});

test('an address that two accounts have (they differ by case only) is refused, never guessed', function () {
    oidcMember('ada@example.com');
    oidcMember('ADA@example.com');

    $response = oidcCallback(oidcStart());

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    expect(oidcLoggedReasons())->toContain('ambiguous_address')->and(UserIdentity::query()->count())->toBe(0);
});

test('an account that is already connected to another account at this provider is not connected by its address', function () {
    $user = oidcMember('ada@example.com');
    UserIdentity::factory()->create(['user_id' => $user->id, 'issuer' => OIDC_ISSUER, 'subject' => 'sub-the-first-one']);

    $response = oidcCallback(oidcStart(), ['sub' => 'sub-a-second-person-with-the-same-address']);

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    expect(oidcLoggedReasons())->toContain('issuer_already_linked')->and(UserIdentity::query()->count())->toBe(1);
});

test('the same subject of another issuer is another account', function () {
    $user = oidcMember('ada@example.com');
    UserIdentity::factory()->create(['user_id' => $user->id, 'issuer' => 'https://other.example.test', 'subject' => 'sub-ada']);

    oidcCallback(oidcStart())->assertRedirect(route('dashboard'));

    expect($user->identities()->count())->toBe(2);
});

test('the visitor who was reading a page comes back to it after the sign-in with the provider (#1387)', function () {
    $c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $c['community']->id]);
    $space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Members]);
    $url = route('spaces.show', ['communitySlug' => $c['community']->slug, 'spaceSlug' => $space->slug]);

    $this->get($url)->assertRedirect(route('login'));
    $start = oidcStart();

    oidcCallback($start, ['email' => $c['member']->email, 'sub' => 'sub-member'])->assertRedirect($url);
    $this->assertAuthenticatedAs($c['member']);
});

test('the session id changes at the sign-in', function () {
    $start = oidcStart();
    $before = session()->getId();

    oidcCallback($start);

    expect(session()->getId())->not->toBe($before);
});

// ---------------------------------------------------------------------------------------------------------------------
// Two-factor authentication
// ---------------------------------------------------------------------------------------------------------------------

test('an account with two-factor authentication is asked for its code after the callback, like after a password', function (string $how) {
    [$user, $secret] = oidcTwoFactorUser();
    if ($how === 'identity') {
        UserIdentity::factory()->create(['user_id' => $user->id, 'issuer' => OIDC_ISSUER, 'subject' => 'sub-ada']);
    }

    oidcCallback(oidcStart())->assertRedirect(route('two-factor.login'));
    // The provider did not sign anybody in
    $this->assertGuest();
    $this->get('/two-factor-challenge')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/TwoFactorChallenge'));

    $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->assertGuest();

    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect();
    $this->assertAuthenticatedAs($user);
})->with(['an identity that exists' => 'identity', 'the first sign-in, by the address' => 'address']);

test('the intended page survives the two-factor challenge', function () {
    [$user, $secret] = oidcTwoFactorUser();
    UserIdentity::factory()->create(['user_id' => $user->id, 'issuer' => OIDC_ISSUER, 'subject' => 'sub-ada']);

    $this->withSession(['url.intended' => 'https://knowii.test/community/x']);
    oidcCallback(oidcStart())->assertRedirect(route('two-factor.login'));
    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect('https://knowii.test/community/x');
});

// ---------------------------------------------------------------------------------------------------------------------
// Connecting from the profile settings
// ---------------------------------------------------------------------------------------------------------------------

test('a signed-in member connects the account: the identity is created, nothing about the member changes', function () {
    $member = oidcMember('ada@example.com');
    $this->actingAs($member);

    // The provider says another address and does not verify it: the member is signed in, so this proves nothing about the address
    $response = oidcCallback(oidcStart(), ['email' => 'else@example.org', 'email_verified' => false]);

    $response->assertRedirect(route('profile.show'))->assertSessionHas('oidc_status')->assertSessionMissing('oidc_error');
    $this->assertAuthenticatedAs($member);
    expect($member->identities()->count())->toBe(1)
        ->and($member->identities()->first()->subject)->toBe('sub-ada')
        ->and($member->fresh()->email)->toBe('ada@example.com')
        ->and(User::query()->count())->toBe(1);
});

test('a provider account that is connected to another member is refused, and nothing changes', function () {
    $owner = oidcMember('first@example.com');
    UserIdentity::factory()->create(['user_id' => $owner->id, 'issuer' => OIDC_ISSUER, 'subject' => 'sub-ada']);
    $member = oidcMember('second@example.com');
    $this->actingAs($member);

    $response = oidcCallback(oidcStart());

    $response->assertRedirect(route('profile.show'))->assertSessionHas('oidc_error')->assertSessionMissing('oidc_status');
    // Still signed in as the member: never switched to the owner of the identity
    $this->assertAuthenticatedAs($member);
    expect($member->identities()->count())->toBe(0)->and($owner->identities()->count())->toBe(1)
        ->and(oidcLoggedReasons())->toContain('subject_linked_elsewhere');
});

test('a member who has an account at this provider already cannot connect a second one', function () {
    $member = oidcMember('ada@example.com');
    UserIdentity::factory()->create(['user_id' => $member->id, 'issuer' => OIDC_ISSUER, 'subject' => 'sub-the-first-one']);
    $this->actingAs($member);

    $response = oidcCallback(oidcStart(), ['sub' => 'sub-the-second-one']);

    $response->assertRedirect(route('profile.show'))->assertSessionHas('oidc_error');
    expect($member->identities()->count())->toBe(1)->and($member->identities()->first()->subject)->toBe('sub-the-first-one')
        ->and(oidcLoggedReasons())->toContain('issuer_already_linked');
});

test('the member connects the same account twice: nothing is added', function () {
    $member = oidcMember('ada@example.com');
    $this->actingAs($member);

    oidcCallback(oidcStart())->assertSessionHas('oidc_status');
    oidcCallback(oidcStart())->assertSessionHas('oidc_status');

    expect($member->identities()->count())->toBe(1);
});

test('a member who signs out, or in as someone else, while the flow runs cannot finish it', function () {
    $first = oidcMember('first@example.com');
    $second = oidcMember('second@example.com');

    // Started by the first member to connect: the second member is signed in at the callback
    $this->actingAs($first);
    $start = oidcStart();
    $this->actingAs($second);
    $response = oidcCallback($start);

    $response->assertRedirect(route('profile.show'))->assertSessionHas('oidc_error');
    expect(UserIdentity::query()->count())->toBe(0)->and(oidcLoggedReasons())->toContain('session_changed');
});

test('a flow that a guest started does not go on when somebody is signed in at the callback', function () {
    $start = oidcStart();
    $member = oidcMember('ada@example.com');
    UserIdentity::factory()->create(['user_id' => $member->id, 'issuer' => OIDC_ISSUER, 'subject' => 'sub-ada']);
    $other = oidcMember('other@example.com');
    $this->actingAs($other);

    $response = oidcCallback($start);

    $response->assertRedirect(route('profile.show'))->assertSessionHas('oidc_error');
    // Not switched to the account of the identity
    $this->assertAuthenticatedAs($other);
});

test('a signed-in member whose address is not verified is sent to verify it, not to the provider', function () {
    $member = User::factory()->unverified()->create();

    $this->actingAs($member)->post('/auth/oidc/connect')->assertRedirect(route('verification.notice'));
    Http::assertNothingSent();
});

test('the link of the sign-in never starts a flow for a signed-in member: another site could open it', function () {
    $member = oidcMember('ada@example.com');

    $this->actingAs($member)->get('/auth/oidc/redirect')->assertRedirect(route('dashboard'))->assertSessionMissing('oidc.flow');
    Http::assertNothingSent();
    expect($member->identities()->count())->toBe(0);
});

test('connecting an account needs a password that was confirmed a moment ago, when the account has one', function () {
    $member = oidcMember('ada@example.com');
    $this->actingAs($member);

    // Not confirmed, and confirmed too long ago
    $this->post('/auth/oidc/connect')->assertStatus(423)->assertSessionMissing('oidc.flow');
    $this->withSession(['auth.password_confirmed_at' => now()->subSeconds(config()->integer('auth.password_timeout') + 60)->getTimestamp()])
        ->post('/auth/oidc/connect')->assertStatus(423)->assertSessionMissing('oidc.flow');
    Http::assertNothingSent();

    // Confirmed: the flow starts, for this member
    $this->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])->post('/auth/oidc/connect')
        ->assertRedirectContains('https://auth.example.test/authorize?')
        ->assertSessionHas('oidc.flow', fn (array $flow): bool => $flow['link_user_id'] === $member->id);
});

test('an account without a password connects without a confirmation, and a guest cannot connect', function () {
    $this->post('/auth/oidc/connect')->assertRedirect(route('login'));

    $member = oidcMember('ada@example.com');
    $member->forceFill(['password' => null])->save();

    $this->actingAs($member->fresh())->post('/auth/oidc/connect')->assertRedirectContains('https://auth.example.test/authorize?');
});

test('the profile settings give the card its data: the connected accounts, without the subject, and whether a password exists', function () {
    $member = oidcMember('ada@example.com');
    UserIdentity::factory()->create(['user_id' => $member->id, 'issuer' => OIDC_ISSUER, 'subject' => 'sub-secret-subject']);

    $this->actingAs($member->fresh())->get('/user/profile')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Profile/Show')
        ->where('hasPassword', true)
        ->has('connectedAccounts', 1)
        ->where('connectedAccounts.0.label', 'Example account')
        ->where('connectedAccounts.0.issuer', OIDC_ISSUER)
        ->where('connectedAccounts.0.current', true)
        ->has('connectedAccounts.0.cuid')
        ->has('connectedAccounts.0.linked_at')
        ->missing('connectedAccounts.0.subject'));

    $member->forceFill(['password' => null])->save();
    $this->actingAs($member->fresh())->get('/user/profile')->assertInertia(fn ($page) => $page->where('hasPassword', false));
});

// ---------------------------------------------------------------------------------------------------------------------
// The API: list and disconnect
// ---------------------------------------------------------------------------------------------------------------------

test('the API lists the connected accounts of the member: the issuer, a label and the dates, never the subject', function () {
    $member = oidcMember('ada@example.com');
    $identity = UserIdentity::factory()->create(['user_id' => $member->id, 'issuer' => OIDC_ISSUER, 'subject' => 'sub-secret-subject', 'last_used_at' => now()]);
    UserIdentity::factory()->create(['subject' => 'sub-of-somebody-else']);
    Sanctum::actingAs($member, ['identities:read']);

    $response = $this->getJson(ApiRoutes::path(ApiRoutes::USER_IDENTITIES))->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.cuid', $identity->cuid)
        ->assertJsonPath('data.0.issuer', OIDC_ISSUER)
        ->assertJsonPath('data.0.label', 'Example account');

    expect($response->json('data.0'))->toHaveKeys(['linked_at', 'last_used_at'])
        ->and($response->getContent())->not->toContain('sub-secret-subject')->not->toContain('sub-of-somebody-else');
});

test('the API removes a connected account; the member keeps the account, the address and the password', function () {
    $member = oidcMember('ada@example.com');
    $identity = UserIdentity::factory()->create(['user_id' => $member->id, 'issuer' => OIDC_ISSUER]);
    Sanctum::actingAs($member, ['identities:write']);

    $this->deleteJson(ApiRoutes::path(ApiRoutes::USER_IDENTITY, [ApiRoutes::PARAM_IDENTITY_CUID => $identity->cuid]))->assertNoContent();

    expect(UserIdentity::query()->count())->toBe(0)->and(User::query()->find($member->id))->not->toBeNull();
    $this->app['auth']->forgetGuards();
    $this->post('/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertRedirect(route('dashboard'));
});

test('the web session may disconnect too, and the other member\'s identity is a 404, not a 403', function () {
    $member = oidcMember('ada@example.com');
    $someoneElse = UserIdentity::factory()->create();
    $mine = UserIdentity::factory()->create(['user_id' => $member->id]);
    $this->actingAs($member);

    $this->deleteJson(ApiRoutes::path(ApiRoutes::USER_IDENTITY, [ApiRoutes::PARAM_IDENTITY_CUID => $someoneElse->cuid]))->assertNotFound();
    $this->deleteJson(ApiRoutes::path(ApiRoutes::USER_IDENTITY, [ApiRoutes::PARAM_IDENTITY_CUID => 'unknown-cuid']))->assertNotFound();
    expect(UserIdentity::query()->count())->toBe(2);

    $this->deleteJson(ApiRoutes::path(ApiRoutes::USER_IDENTITY, [ApiRoutes::PARAM_IDENTITY_CUID => $mine->cuid]))->assertNoContent();
    expect(UserIdentity::query()->whereKey($mine->id)->exists())->toBeFalse();
});

test('the identity routes of the API need a sign-in and the token abilities', function () {
    $member = oidcMember('ada@example.com');
    $identity = UserIdentity::factory()->create(['user_id' => $member->id]);
    $path = ApiRoutes::path(ApiRoutes::USER_IDENTITY, [ApiRoutes::PARAM_IDENTITY_CUID => $identity->cuid]);

    $this->getJson(ApiRoutes::path(ApiRoutes::USER_IDENTITIES))->assertUnauthorized();
    $this->deleteJson($path)->assertUnauthorized();

    Sanctum::actingAs($member, ['user:read', 'user:write', 'communities:read', 'communities:write']);
    $this->getJson(ApiRoutes::path(ApiRoutes::USER_IDENTITIES))->assertForbidden();
    $this->deleteJson($path)->assertForbidden();

    Sanctum::actingAs($member, ['identities:read']);
    $this->getJson(ApiRoutes::path(ApiRoutes::USER_IDENTITIES))->assertOk();
    $this->deleteJson($path)->assertForbidden();

    Sanctum::actingAs($member, ['identities:write']);
    $this->getJson(ApiRoutes::path(ApiRoutes::USER_IDENTITIES))->assertForbidden();
    expect(UserIdentity::query()->count())->toBe(1);
    $this->deleteJson($path)->assertNoContent();
});

test('the abilities exist and are not in the default set of a new token', function () {
    expect(Jetstream::$permissions)->toContain('identities:read', 'identities:write')
        ->and(Jetstream::$defaultPermissions)->not->toContain('identities:read')->not->toContain('identities:write');
});

// ---------------------------------------------------------------------------------------------------------------------
// Personal data
// ---------------------------------------------------------------------------------------------------------------------

test('the member export holds the connected accounts: the issuer, the subject of the member and the dates', function () {
    $member = oidcMember('ada@example.com');
    UserIdentity::factory()->create(['user_id' => $member->id, 'issuer' => OIDC_ISSUER, 'subject' => 'sub-of-the-member', 'last_used_at' => now()]);
    UserIdentity::factory()->create(['subject' => 'sub-of-somebody-else']);

    expect(Artisan::call('users:export', ['email' => 'ada@example.com']))->toBe(0);
    $path = trim(Artisan::output());
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $account = json_decode((string) $zip->getFromName('account.json'), true);
    $readme = (string) $zip->getFromName('README.txt');
    $zip->close();
    File::delete($path);

    expect($account['connected_accounts'])->toHaveCount(1)
        ->and($account['connected_accounts'][0]['issuer'])->toBe(OIDC_ISSUER)
        ->and($account['connected_accounts'][0]['subject'])->toBe('sub-of-the-member')
        ->and($account['connected_accounts'][0])->toHaveKeys(['cuid', 'linked_at', 'last_used_at'])
        ->and(json_encode($account))->not->toContain('sub-of-somebody-else')
        ->and($readme)->toContain('accounts of other providers');
});

test('deleting the account deletes its identities, and a later sign-in creates a new, empty account (ADR 0024)', function () {
    $member = oidcMember('ada@example.com');
    UserIdentity::factory()->create(['user_id' => $member->id, 'issuer' => OIDC_ISSUER, 'subject' => 'sub-ada']);
    $oldCuid = $member->cuid;

    app(DeletesAccounts::class)->delete($member);

    expect(UserIdentity::query()->count())->toBe(0)->and(User::query()->count())->toBe(0);

    oidcCallback(oidcStart())->assertRedirect(route('dashboard'));
    $new = User::query()->firstOrFail();
    expect($new->cuid)->not->toBe($oldCuid)->and($new->identities()->count())->toBe(1);
});

test('the logs hold cuids and the issuer, never a token, a code, a subject or an address', function () {
    // A failure, then a first sign-in, a second sign-in and a connection
    $start = oidcStart();
    oidcCallback($start, ['nonce' => 'another-nonce']);
    oidcCallback(oidcStart());
    $this->post('/logout');
    oidcCallback(oidcStart());

    $logged = json_encode($this->logged->getArrayCopy());
    expect(oidcLoggedReasons())->toContain('wrong_nonce')
        ->and($logged)->not->toContain($this->idToken)
        ->and($logged)->not->toContain('auth-code')
        ->and($logged)->not->toContain('AT-SECRET')
        ->and($logged)->not->toContain('RT-SECRET')
        ->and($logged)->not->toContain('sub-ada')
        ->and($logged)->not->toContain('ada@example.com')
        ->and($logged)->not->toContain(OIDC_CLIENT_SECRET)
        ->and($logged)->not->toContain($start['state'])
        ->and($logged)->toContain(User::query()->firstOrFail()->cuid)
        ->and($logged)->toContain('auth.example.test');
});

test('the message on the sign-in page is plain: no token, no raw error, no detail', function () {
    $start = oidcStart();
    oidcCallback($start, ['aud' => 'another-client']);

    $this->get('/login')->assertInertia(fn ($page) => $page
        ->where('oidc.error', fn (string $error) => $error === 'We could not sign you in with this account. Please try again, or use another way to sign in.'
            && ! str_contains($error, 'aud') && ! str_contains($error, $this->idToken)));
});

test('the message is shown once', function () {
    oidcCallback(oidcStart(), ['aud' => 'another-client']);

    $this->get('/login')->assertInertia(fn ($page) => $page->where('oidc.error', fn ($error) => is_string($error)));
    $this->get('/login')->assertInertia(fn ($page) => $page->where('oidc.error', null));
});

test('the fake provider is replaceable behind its contract', function () {
    expect(app(ReadsOpenIdConnectIdentities::class))->toBeInstanceOf(OpenIdConnectClient::class);
});

// ---------------------------------------------------------------------------------------------------------------------
// Rate limits
// ---------------------------------------------------------------------------------------------------------------------

test('the redirect and the callback are rate limited per IP address', function () {
    for ($index = 0; $index < 10; $index++) {
        $this->get('/auth/oidc/redirect')->assertRedirect();
    }

    $this->get('/auth/oidc/redirect')->assertTooManyRequests();
    $this->get('/auth/oidc/callback?code=x&state=y')->assertTooManyRequests();
});

test('crawlers never start a sign-in flow: robots.txt refuses /auth/', function () {
    config(['robots.allow_indexing' => true]);

    expect((string) $this->get('/robots.txt')->getContent())->toContain('Disallow: /auth/');
});
