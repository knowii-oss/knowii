<?php

use App\Actions\Resources\CaptureResourcePage;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Mail\FinishSignUp;
use App\Models\Community;
use App\Models\User;
use App\Models\UserIdentity;
use Firebase\JWT\JWT;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A capturer (ADR 0018) whose HTTP calls are faked, in order: the page (GET), then the icon of the site. The HTML
 * must not reference a cover image, else add its answer.
 *
 * @param  list<Response>  $more  Answers after the page (default: the icon is missing)
 */
function fakeCapturer(string $html, string $contentType = 'text/html; charset=utf-8', int $status = 200, array $more = []): CaptureResourcePage
{
    $capturer = new CaptureResourcePage;
    $capturer->setHttpHandlerStack(HandlerStack::create(new MockHandler([
        new Response($status, ['Content-Type' => $contentType], $html),
        ...($more === [] ? [new Response(404, [], '')] : $more),
    ])));

    return $capturer;
}

/**
 * A community with one user per role, and a user outside of it.
 *
 * @return array{community: Community, owner: User, admin: User, moderator: User, member: User, outsider: User}
 */
function communityWithMembers(KnowiiCommunityVisibility $visibility = KnowiiCommunityVisibility::Private): array
{
    $owner = User::factory()->withUserProfile()->create();
    $community = Community::factory()->create(['owner_id' => $owner->id, 'visibility' => $visibility]);

    $users = ['community' => $community, 'owner' => $owner];

    foreach (['admin' => KnowiiCommunityMemberRole::Admin, 'moderator' => KnowiiCommunityMemberRole::Moderator, 'member' => KnowiiCommunityMemberRole::Member] as $key => $role) {
        $user = User::factory()->withUserProfile()->create();
        $community->users()->attach($user, ['role' => $role]);
        $users[$key] = $user;
    }

    $users['outsider'] = User::factory()->withUserProfile()->create();

    /** @var array{community: Community, owner: User, admin: User, moderator: User, member: User, outsider: User} $users */
    return $users;
}

/*
|--------------------------------------------------------------------------
| The fake sign-in provider of the entitlements (#1386, ADR 0047)
|--------------------------------------------------------------------------
|
| HTTP fakes for the discovery document, the keys, the token endpoint (client credentials) and the read of one subject, and
| change calls that the test signs with an Ed25519 key made at run time. No real provider is ever called.
*/

const ENT_ISSUER = 'https://auth.example.test';
const ENT_CLIENT_ID = 'knowii-test-client';
const ENT_CLIENT_SECRET = 'a-secret-that-is-not-real';
const ENT_ACCESS_TOKEN = 'ENT-ACCESS-TOKEN-';
const ENT_EVENT_TYPE = 'entitlements-event+jwt';
const ENT_WEBHOOK = '/webhooks/oidc/entitlements';

function entB64(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

/**
 * The configuration of an instance that reads entitlements, for the communities of the given slugs
 *
 * @param  list<string>  $slugs
 * @param  array<string, string|null>  $override
 */
function entConfigure(array $slugs = [], array $override = []): void
{
    config([
        'app.url' => 'https://knowii.test',
        'services.oidc' => [
            'issuer' => ENT_ISSUER,
            'client_id' => ENT_CLIENT_ID,
            'client_secret' => ENT_CLIENT_SECRET,
            'scopes' => 'openid profile email',
            'label' => 'Example account',
            'entitlements_url' => ENT_ISSUER.'/v1/subjects/{subject}/entitlements',
            'entitlements_scope' => 'entitlements:read:subjects',
            'entitlements_resource' => ENT_ISSUER.'/v1',
            'entitlements_sign_in_scope' => 'entitlements:read',
            'entitlement_communities' => implode(',', $slugs),
            ...$override,
        ],
    ]);
}

/**
 * @return array{secret: string, public: string, jwk: array<string, string>}
 */
function entEdKey(string $kid): array
{
    $pair = sodium_crypto_sign_keypair();
    $public = sodium_crypto_sign_publickey($pair);

    return [
        'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
        'public' => $public,
        'jwk' => ['kty' => 'OKP', 'crv' => 'Ed25519', 'kid' => $kid, 'use' => 'sig', 'alg' => 'EdDSA', 'x' => entB64($public)],
    ];
}

/**
 * The provider: its key, what the test wants it to answer, and what it was asked. `answers` maps a subject to
 * `['status' => 200, 'entitlements' => [...]]`, a status (404, 500...), `'timeout'` or `'garbage'`.
 */
function entFakeProvider(): stdClass
{
    $provider = new stdClass;
    $provider->ed = entEdKey('ed-1');
    $provider->jwks = [$provider->ed['jwk']];
    $provider->tokenStatus = 200;
    $provider->tokenExpiresIn = 600;
    $provider->tokenRequests = [];
    $provider->reads = [];
    $provider->answers = [];

    Http::preventStrayRequests();
    Http::fake([
        'auth.example.test/.well-known/openid-configuration' => fn () => Http::response([
            'issuer' => ENT_ISSUER,
            'authorization_endpoint' => ENT_ISSUER.'/authorize',
            'token_endpoint' => ENT_ISSUER.'/token',
            'jwks_uri' => ENT_ISSUER.'/jwks',
            'response_types_supported' => ['code'],
            'code_challenge_methods_supported' => ['S256'],
            'id_token_signing_alg_values_supported' => ['EdDSA'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
        ]),
        'auth.example.test/jwks' => fn () => Http::response(['keys' => $provider->jwks]),
        'auth.example.test/token' => function (ClientRequest $request) use ($provider) {
            $provider->tokenRequests[] = $request;
            if ($provider->tokenStatus !== 200) {
                return Http::response(['error' => 'invalid_client'], $provider->tokenStatus);
            }

            return Http::response(['access_token' => ENT_ACCESS_TOKEN.count($provider->tokenRequests), 'token_type' => 'Bearer', 'expires_in' => $provider->tokenExpiresIn]);
        },
        'auth.example.test/v1/subjects/*' => function (ClientRequest $request) use ($provider) {
            $subject = rawurldecode(explode('/', trim((string) parse_url($request->url(), PHP_URL_PATH), '/'))[2] ?? '');
            $provider->reads[] = ['subject' => $subject, 'authorization' => $request->header('Authorization')[0] ?? ''];
            $answer = $provider->answers[$subject] ?? 404;
            // A closure answers by the number of reads so far (the first read, the second one)
            if ($answer instanceof Closure) {
                $answer = $answer(count($provider->reads));
            }

            if ($answer === 'timeout') {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }
            if ($answer === 'garbage') {
                return Http::response('<html>not json</html>', 200);
            }
            if (is_int($answer)) {
                return Http::response(['error' => 'x'], $answer);
            }

            return Http::response(['sub' => $answer['sub'] ?? $subject, 'entitlements' => $answer['entitlements'], 'grants' => [['source' => 'secret-source']], 'checked_at' => '2026-11-16T10:00:00Z'], $answer['status'] ?? 200);
        },
    ]);

    return $provider;
}

/**
 * The provider says that the subject holds these keys
 *
 * @param  list<string>  $keys
 */
function entGrant(stdClass $provider, string $subject, array $keys): void
{
    $provider->answers[$subject] = ['status' => 200, 'entitlements' => $keys];
}

/**
 * A connected account of the provider of the instance, for a member
 */
function entIdentity(User $user, string $subject = 'sub-ada'): UserIdentity
{
    return UserIdentity::factory()->create(['user_id' => $user->id, 'issuer' => ENT_ISSUER, 'subject' => $subject]);
}

/**
 * The claims of a good change call
 *
 * @param  array<string, mixed>  $override
 * @param  list<string>  $without
 * @return array<string, mixed>
 */
function entEventClaims(array $override = [], array $without = []): array
{
    $claims = [
        'iss' => ENT_ISSUER,
        'aud' => ENT_CLIENT_ID,
        'sub' => 'sub-ada',
        'jti' => 'jti-'.bin2hex(random_bytes(6)),
        'iat' => now()->getTimestamp() - 5,
        'exp' => now()->getTimestamp() + 295,
        'event' => 'entitlements.changed',
        ...$override,
    ];

    return array_diff_key($claims, array_flip($without));
}

/**
 * A change call signed by the provider (its key and `typ`, unless the test changes them)
 *
 * @param  array<string, mixed>  $claims
 */
function entEventToken(stdClass $provider, array $claims, string $type = ENT_EVENT_TYPE, ?string $secret = null, ?string $kid = 'ed-1'): string
{
    return JWT::encode($claims, $secret ?? $provider->ed['secret'], 'EdDSA', $kid, ['typ' => $type]);
}

/**
 * A token with a header and a signature of the test's choice (the library refuses to make some of them)
 *
 * @param  array<string, mixed>  $header
 * @param  array<string, mixed>  $claims
 */
function entForge(array $header, array $claims, string $signature = ''): string
{
    return entB64((string) json_encode($header)).'.'.entB64((string) json_encode($claims)).'.'.entB64($signature);
}

/**
 * The legal texts of the fixtures (tests/Fixtures/legal) without the given files, by file name: what a test gives to
 * useLegalFolder() for an instance that has every text but these (#1653)
 *
 * @return array<string, string>
 */
function legalTextsWithout(string ...$without): array
{
    $files = [];
    foreach (glob(dirname(__DIR__).'/tests/Fixtures/legal/*') ?: [] as $path) {
        if (! in_array(basename($path), $without, true)) {
            $files[basename($path)] = (string) file_get_contents($path);
        }
    }

    return $files;
}

/**
 * An instance with its two required values (#1653, ADR 0055) and a session cookie of one host (ADR 0068). A test that
 * switches to production calls it first: in production the placeholders of the test environment count as not set, the
 * development name of the session cookie is refused, and every request answers 503.
 */
function configuredInstance(): void
{
    config([
        'legal.operator' => 'Example Ltd (Ireland)',
        'legal.contact_email' => 'hello@example.com',
        'session.cookie' => '__Host-knowii_session',
        'session.domain' => null,
    ]);
}

/**
 * Everything that the application logs from now on: the messages and the contexts, as one text each
 */
function entCaptureLogs(): ArrayObject
{
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = $event->message.' '.json_encode($event->context, JSON_UNESCAPED_SLASHES);
    });

    return $logged;
}

/**
 * The token of the last mail "Finish your sign-up" to the address (ADR 0065): from the fake of the mailer when the test
 * fakes it, else from the link of the last such mail of the array mailer. Null: no such mail
 */
function signUpLinkToken(string $email): ?string
{
    $mailer = Mail::getFacadeRoot();
    if ($mailer instanceof MailFake) {
        $mail = Mail::queued(FinishSignUp::class, static fn (FinishSignUp $mail): bool => $mail->hasTo($email))->last()
            ?? Mail::sent(FinishSignUp::class, static fn (FinishSignUp $mail): bool => $mail->hasTo($email))->last();

        return $mail instanceof FinishSignUp ? $mail->token : null;
    }

    $transport = Mail::mailer('array')->getSymfonyTransport();
    if (! $transport instanceof ArrayTransport) {
        return null;
    }
    foreach ($transport->messages()->reverse() as $message) {
        $original = $message->getOriginalMessage();
        $to = method_exists($original, 'getTo') ? array_map(static fn ($address) => $address->getAddress(), $original->getTo()) : [];
        $body = method_exists($original, 'getTextBody') ? (string) $original->getTextBody() : '';
        if (in_array($email, $to, true) && preg_match('#/register/link/([A-Za-z0-9]{64})#', $body, $match) === 1) {
            return $match[1];
        }
    }

    return null;
}

/**
 * The whole sign-up, as a person does it (ADR 0065): the form (the name and the address), the work of the queue, then the
 * page of the link of the mail (the name, the password, the terms, the zone). The fields of both are in one list; the
 * ones of the page get the defaults of a valid page. Returns the answer of the page of the link
 *
 * @param  array<string, mixed>  $fields
 */
function signUpThroughLink(array $fields): TestResponse
{
    $fields = ['terms' => true, ...$fields];
    if (! array_key_exists('password_confirmation', $fields) && array_key_exists('password', $fields)) {
        $fields['password_confirmation'] = $fields['password'];
    }

    $form = array_intersect_key($fields, array_flip(['name', 'email', 'turnstile_token', 'website']));
    test()->post('/register', $form)->assertSessionHasNoErrors();
    if (config('queue.default') !== 'sync') {
        Artisan::call('queue:work', ['connection' => config('queue.default'), '--stop-when-empty' => true, '--queue' => 'default', '--sleep' => 0, '--tries' => 1]);
    }

    $email = is_string($fields['email'] ?? null) ? mb_strtolower($fields['email']) : '';
    $token = signUpLinkToken($email);
    expect($token)->not->toBeNull();

    $page = array_intersect_key($fields, array_flip(['name', 'password', 'password_confirmation', 'terms', 'time_zone']));

    return test()->post('/register/link/'.$token, $page);
}
