<?php

use App\ApiRoutes;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

/*
 * Every answer of the web and of the API has the security headers of config/secure-headers.php, also an answer that a
 * middleware gives (a redirect to the sign-in page, a 404 of a route binding, a 403, a 419, a 429, the 503 of an instance
 * that is not configured, the check of the email address) and the error page as production renders it (a 404, a 500). The global middleware of the headers is first in
 * the stack (bootstrap/app.php), and the nonce of the Content Security Policy is the nonce of the scripts of the page.
 */

/**
 * The headers that the configuration sets with a fixed value
 */
const SH_FIXED_HEADERS = [
    'X-Content-Type-Options' => 'x-content-type-options',
    'X-Download-Options' => 'x-download-options',
    'X-Frame-Options' => 'x-frame-options',
    'X-Permitted-Cross-Domain-Policies' => 'x-permitted-cross-domain-policies',
    'Referrer-Policy' => 'referrer-policy',
    'Cross-Origin-Embedder-Policy' => 'cross-origin-embedder-policy',
    'Cross-Origin-Opener-Policy' => 'cross-origin-opener-policy',
    'Cross-Origin-Resource-Policy' => 'cross-origin-resource-policy',
];

/**
 * The Content Security Policy without its nonces, which change on every answer
 */
function shPolicyWithoutNonces(?string $header): string
{
    return (string) preg_replace("/ 'nonce-[^']+'/", '', (string) $header);
}

/**
 * The nonces of one directive of the Content Security Policy
 *
 * @return list<string>
 */
function shPolicyNonces(TestResponse $response, string $directive): array
{
    foreach (explode(';', (string) $response->headers->get('Content-Security-Policy')) as $part) {
        $sources = preg_split('/\s+/', trim($part)) ?: [];
        if (array_shift($sources) === $directive) {
            return array_values(array_map(
                static fn (string $source): string => substr($source, strlen("'nonce-"), -1),
                array_filter($sources, static fn (string $source): bool => str_starts_with($source, "'nonce-")),
            ));
        }
    }

    return [];
}

/**
 * Runs the callback as production does, on an instance with its operator and its contact address (or without them)
 */
function shAsInProduction(Closure $callback, bool $configured = true): TestResponse
{
    if ($configured) {
        configuredInstance();
    } else {
        config(['legal.operator' => '', 'legal.contact_email' => '']);
    }
    app()->detectEnvironment(static fn (): string => 'production');
    try {
        return $callback();
    } finally {
        app()->detectEnvironment(static fn (): string => 'testing');
    }
}

/**
 * The signed link of an invitation that is gone: its route binding answers 404
 */
function shGoneInvitationLink(): string
{
    return URL::temporarySignedRoute('community-invitations.accept', now()->addDay(), ['invitation' => 'gone-invitation']);
}

/**
 * A request of a new personal access token of a member, with these abilities
 *
 * @param  list<string>  $abilities
 */
function shWithToken(array $abilities, string $url): TestResponse
{
    $token = User::factory()->withUserProfile()->create()->createToken('script', $abilities)->plainTextToken;
    app('auth')->forgetGuards();

    return test()->withToken($token)->getJson($url);
}

/**
 * A route of the group that fails with an exception: a 500
 */
function shFailingRoute(string $group): string
{
    $path = $group === 'api' ? '/api/qx-security-headers-failure' : '/qx-security-headers-failure';
    Route::middleware($group)->get($path, static fn () => throw new RuntimeException('A failure of the test'));

    return $path;
}

/**
 * The answer of one case of the dataset
 */
function shAnswer(string $case): TestResponse
{
    $test = test();
    $missingCommunity = ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => 'nothing-here']);

    return match ($case) {
        'web: the redirect of a visitor to the sign-in page' => $test->get('/dashboard'),
        'web: a 404 of a route binding' => $test->actingAs(User::factory()->withUserProfile()->create())->get(shGoneInvitationLink()),
        'web: a 403 of the signature check' => $test->actingAs(User::factory()->withUserProfile()->create())->get('/user/email-change/someone/a-hash'),
        'web: a 419' => shAsInProduction(static fn (): TestResponse => $test->actingAs(User::factory()->withUserProfile()->create())->post('/logout')),
        'web: a 429' => (static function () use ($test): TestResponse {
            RateLimiter::for('magic-link', static fn (): Limit => Limit::perMinute(1)->by('security-headers'));
            $test->post('/login/link', ['email' => 'ada@example.com']);

            return $test->post('/login/link', ['email' => 'ada@example.com']);
        })(),
        'web: the 503 of an instance that is not configured' => shAsInProduction(static fn (): TestResponse => $test->get('/'), configured: false),
        'web: the error page of a 404 of a route binding, as in production' => shAsInProduction(static fn (): TestResponse => $test->actingAs(User::factory()->withUserProfile()->create())->get(shGoneInvitationLink())),
        'web: the error page of an address that matches no route, as in production' => shAsInProduction(static fn (): TestResponse => $test->get('/no-such-page')),
        'web: the error page of a 500, as in production' => shAsInProduction(static fn (): TestResponse => $test->get(shFailingRoute('web'))),
        'web: the redirect of an account whose email address is not verified' => $test->actingAs(User::factory()->withUserProfile()->unverified()->create())->get('/dashboard'),
        'api: the 401 of the sign-in check' => $test->getJson(ApiRoutes::path(ApiRoutes::USER)),
        'api: a 404 of a route binding' => shWithToken(['communities:read'], $missingCommunity),
        'api: a 403 of a token ability' => shWithToken(['posts:read'], ApiRoutes::path(ApiRoutes::USER)),
        'api: a 419' => shAsInProduction(static fn (): TestResponse => $test->withHeader('Referer', (string) config('app.url'))->postJson(ApiRoutes::path(ApiRoutes::COMMUNITIES), [])),
        'api: a 429' => (static function () use ($test, $missingCommunity): TestResponse {
            RateLimiter::for('public-reads', static fn (): Limit => Limit::perMinute(1)->by('security-headers'));
            $test->getJson($missingCommunity);

            return $test->getJson($missingCommunity);
        })(),
        'api: the 503 of an instance that is not configured' => shAsInProduction(static fn (): TestResponse => $test->getJson(ApiRoutes::path(ApiRoutes::COMMUNITIES)), configured: false),
        'api: the answer of a 404 of a route binding, as in production' => shAsInProduction(static fn (): TestResponse => shWithToken(['communities:read'], $missingCommunity)),
        'api: the answer of a 500, as in production' => shAsInProduction(static fn (): TestResponse => $test->getJson(shFailingRoute('api'))),
        'api: the 403 of the check of the email address' => $test->actingAs(User::factory()->withUserProfile()->unverified()->create())->getJson(ApiRoutes::path(ApiRoutes::USER)),
        default => throw new LogicException("Unknown case {$case}"),
    };
}

test('every answer has the security headers of the configuration', function (string $case, int $status) {
    $page = $this->get('/')->assertOk();
    app('auth')->forgetGuards();

    $response = shAnswer($case)->assertStatus($status);

    foreach (SH_FIXED_HEADERS as $header => $key) {
        expect($response->headers->get($header))->toBe(config("secure-headers.{$key}"), $header);
    }
    expect($response->headers->get('Permissions-Policy'))->not->toBeNull()->toBe($page->headers->get('Permissions-Policy'))
        ->and($response->headers->get('Strict-Transport-Security'))->not->toBeNull()->toBe($page->headers->get('Strict-Transport-Security'))
        // One policy, the one of every page
        ->and($response->headers->all('Content-Security-Policy'))->toHaveCount(1)
        ->and(shPolicyWithoutNonces($response->headers->get('Content-Security-Policy')))->toBe(shPolicyWithoutNonces($page->headers->get('Content-Security-Policy')));
})->with([
    ['web: the redirect of a visitor to the sign-in page', 302],
    ['web: a 404 of a route binding', 404],
    ['web: a 403 of the signature check', 403],
    ['web: a 419', 302],
    ['web: a 429', 429],
    ['web: the 503 of an instance that is not configured', 503],
    ['web: the error page of a 404 of a route binding, as in production', 404],
    ['web: the error page of an address that matches no route, as in production', 404],
    ['web: the error page of a 500, as in production', 500],
    ['web: the redirect of an account whose email address is not verified', 302],
    ['api: the 401 of the sign-in check', 401],
    ['api: a 404 of a route binding', 404],
    ['api: a 403 of a token ability', 403],
    ['api: a 419', 302],
    ['api: a 429', 429],
    ['api: the 503 of an instance that is not configured', 503],
    ['api: the answer of a 404 of a route binding, as in production', 404],
    ['api: the answer of a 500, as in production', 500],
    ['api: the 403 of the check of the email address', 403],
]);

test('the nonce of the scripts and of the styles of a page is in the Content Security Policy, also on the error page', function () {
    $pages = [
        'the home page' => $this->get('/')->assertOk(),
        'the error page of a 404 of a route binding' => shAsInProduction(fn (): TestResponse => $this->actingAs(User::factory()->withUserProfile()->create())->get(shGoneInvitationLink()))
            ->assertNotFound()->assertInertia(fn ($page) => $page->component('Error')->where('status', 404)),
        'the page without access' => (function (): TestResponse {
            $c = communityWithMembers();

            return $this->actingAs($c['outsider'])->get(route('communities.about', ['communitySlug' => $c['community']->slug]));
        })()->assertForbidden()->assertInertia(fn ($page) => $page->component('Communities/NoAccess')),
    ];

    foreach ($pages as $name => $response) {
        $html = (string) $response->getContent();
        // The scripts that the browser runs: the data of the page (application/json) is not one
        preg_match_all('/<script\b(?![^>]*application\/json)[^>]*>/i', $html, $scripts);
        preg_match_all('/<script\b(?![^>]*application\/json)[^>]*\bnonce="([^"]+)"/i', $html, $scriptNonces);
        preg_match('/<meta property="csp-nonce" nonce="([^"]+)"/', $html, $styleNonce);

        // Every script of the page has a nonce, the module of Vite too, and the policy names each of them
        expect($scripts[0])->not->toBeEmpty($name)
            ->and($scriptNonces[1])->toHaveCount(count($scripts[0]), $name)
            ->and($html)->toMatch('/<script\b[^>]*type="module"[^>]*nonce="[^"]+"|<script\b[^>]*nonce="[^"]+"[^>]*type="module"/i')
            ->and(array_diff($scriptNonces[1], shPolicyNonces($response, 'script-src')))->toBe([], $name)
            ->and($styleNonce[1] ?? null)->not->toBeNull($name)
            ->and(shPolicyNonces($response, 'style-src'))->toContain($styleNonce[1] ?? '');
    }
});
