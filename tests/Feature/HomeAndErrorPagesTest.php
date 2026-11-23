<?php

use App\ApiRoutes;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * The home page and the error page (#1574). The home page has no waitlist and says whether sign-up is open. The waitlist
 * route is gone. Outside the local and testing environments, the error page is the component `Error` with the HTTP
 * status, and it knows who is signed in, also on an address that matches no route.
 */

const HOME_ERROR_STATUSES = [403, 404, 500, 503];

/**
 * A route of the web group that answers with the given status, for the tests of the error page
 */
function homeErrorRoute(): string
{
    Route::middleware('web')->get('/home-error-test/{status}', static function (string $status) {
        abort((int) $status);
    });

    return '/home-error-test';
}

test('the home page says whether sign-up is open, and has no waitlist', function () {
    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Welcome')
        ->where('canLogin', true)
        ->where('canRegister', true)
        ->missing('waitlist'));
});

test('the waitlist route is gone', function () {
    $this->postJson('/api/v1/waitlist', ['email' => 'ada@example.com', 'turnstileToken' => 'token-1'])->assertNotFound();
    $this->post('/api/v1/waitlist')->assertNotFound();
});

test('the error page renders the component Error with the status, for a visitor and for a signed-in person', function () {
    $base = homeErrorRoute();
    configuredInstance();
    app()->detectEnvironment(fn () => 'production');

    foreach (HOME_ERROR_STATUSES as $status) {
        $this->get("{$base}/{$status}")->assertStatus($status)
            ->assertInertia(fn ($page) => $page->component('Error')->where('status', $status)->where('auth.user', null));
    }

    $user = User::factory()->withUserProfile()->create();
    foreach (HOME_ERROR_STATUSES as $status) {
        $this->actingAs($user)->get("{$base}/{$status}")->assertStatus($status)
            ->assertInertia(fn ($page) => $page->component('Error')->where('status', $status)->where('auth.user.cuid', $user->cuid));
    }
});

test('an address that matches no route answers 404 and the error page knows who is signed in', function () {
    configuredInstance();
    app()->detectEnvironment(fn () => 'production');
    $user = User::factory()->withUserProfile()->create();

    $this->actingAs($user)->get('/no-such-address/at-all')->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404)->where('auth.user.cuid', $user->cuid));

    // The other methods answer 404 too, not 405, and without the CSRF check (nothing changes)
    $this->actingAs($user)->post('/no-such-address')->assertNotFound();
    $this->actingAs($user)->delete('/no-such-address')->assertNotFound();
});

test('an API address that matches no route keeps the JSON 404 of the API', function () {
    $this->getJson('/api/v1/no-such-endpoint')->assertNotFound()->assertJsonPath('type', 'notFound');
    $this->postJson('/api/v1/no-such-endpoint')->assertNotFound()->assertJsonPath('type', 'notFound');
});

/*
 * An API error stays JSON in every environment (#1716, ADR 0011): the error page is for a page request and an Inertia
 * visit only. Every error of an address of the API is JSON, whatever the `Accept` header says
 */

/**
 * The routes of the api group that fail on purpose, for the tests of the JSON errors: an exception with a text that the
 * answer may never show, a 503, an expired form token and a validation
 */
function homeApiErrorRoutes(): string
{
    Route::middleware('api')->get('/api/home-error-test/exception', static function () {
        throw new RuntimeException('SECRET-TEXT-OF-THE-EXCEPTION');
    });
    Route::middleware('api')->get('/api/home-error-test/unavailable', static function () {
        abort(503);
    });
    Route::middleware('api')->get('/api/home-error-test/expired', static function () {
        throw new TokenMismatchException;
    });
    Route::middleware('api')->post('/api/home-error-test/validation', static function (Request $request) {
        $request->validate(['name' => ['required', 'string']]);
    });

    return '/api/home-error-test';
}

/**
 * A JSON error of the API (ADR 0011): its status, its type, the JSON content type, and no Inertia page
 */
function homeAssertApiError(TestResponse $response, int $status, string $type): void
{
    $response->assertStatus($status)->assertHeader('Content-Type', 'application/json')->assertJsonPath('type', $type);
    expect($response->headers->get('X-Inertia'))->toBeNull()
        ->and((string) $response->getContent())->not->toContain('"component"')->not->toContain('data-page');
}

/**
 * The production environment, with a configured instance
 */
function homeProduction(): void
{
    configuredInstance();
    app()->detectEnvironment(fn () => 'production');
}

test('in production, a request that expects JSON gets the JSON error of the API for 404, 403, 500 and 503', function () {
    $base = homeApiErrorRoutes();
    $c = communityWithMembers();
    homeProduction();

    homeAssertApiError($this->getJson('/api/v1/no-such-endpoint'), 404, 'notFound');
    homeAssertApiError($this->actingAs($c['member'])->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => 'nothing-here'])), 404, 'notFound');

    homeAssertApiError($this->actingAs($c['member'])->patchJson(ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $c['community']->cuid]), ['name' => 'Taken over']), 403, 'authorizationIssue');
    $this->app['auth']->forgetGuards();
    $token = $c['member']->createToken('posts only', ['posts:read'])->plainTextToken;
    homeAssertApiError($this->withToken($token)->getJson('/api/v1/communities'), 403, 'authorizationIssue');
    $this->flushHeaders();
    $this->app['auth']->forgetGuards();

    $failed = $this->getJson("{$base}/exception");
    homeAssertApiError($failed, 500, 'internalError');
    $failed->assertJsonPath('message', 'Internal Server Error');
    expect((string) $failed->getContent())->not->toContain('SECRET-TEXT-OF-THE-EXCEPTION');

    homeAssertApiError($this->getJson("{$base}/unavailable"), 503, 'httpIssue');
});

test('in production, an Inertia visit of an address that answers 404 gets the component Error as an Inertia answer', function () {
    $base = homeErrorRoute();
    homeProduction();
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ];

    $this->get("{$base}/404", $headers)->assertNotFound()->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Error')->assertJsonPath('props.status', 404);
});

test('in production, a request that expects JSON keeps the JSON 404 of an address with no route', function () {
    homeProduction();

    homeAssertApiError($this->getJson('/no-such-address/at-all'), 404, 'notFound');
});

test('every error of an address of the API is JSON, whatever the Accept header says', function (string $environment) {
    $base = homeApiErrorRoutes();
    $c = communityWithMembers();
    if ($environment === 'production') {
        homeProduction();
    }
    $any = ['Accept' => '*/*'];

    homeAssertApiError($this->get('/api/v1/communities', $any), 401, 'authenticationIssue');
    homeAssertApiError($this->json('POST', "{$base}/validation", [], $any), 400, 'validationIssue');
    homeAssertApiError($this->get('/api/v1/no-such-endpoint', $any), 404, 'notFound');
    homeAssertApiError($this->post('/api/v1/no-such-endpoint', [], $any), 404, 'notFound');
    homeAssertApiError($this->actingAs($c['member'])->json('PATCH', ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $c['community']->cuid]), ['name' => 'Taken over'], $any), 403, 'authorizationIssue');
    $this->app['auth']->forgetGuards();

    $failed = $this->get("{$base}/exception", $any);
    homeAssertApiError($failed, 500, 'internalError');
    expect((string) $failed->getContent())->not->toContain('SECRET-TEXT-OF-THE-EXCEPTION');
    homeAssertApiError($this->get("{$base}/unavailable", $any), 503, 'httpIssue');
})->with(['testing', 'production']);

test('an expired form token is the JSON 419 of the API for a request that expects JSON and for the API, a redirect back for a page', function (string $environment) {
    $base = homeApiErrorRoutes();
    Route::middleware('web')->post('/home-error-test/expired', static function () {
        throw new TokenMismatchException;
    });
    if ($environment === 'production') {
        homeProduction();
    }

    homeAssertApiError($this->getJson("{$base}/expired"), 419, 'httpIssue');
    homeAssertApiError($this->get("{$base}/expired", ['Accept' => '*/*']), 419, 'httpIssue');
    homeAssertApiError($this->postJson('/home-error-test/expired'), 419, 'httpIssue');

    $this->from('/dashboard')->post('/home-error-test/expired')->assertRedirect('/dashboard')
        ->assertSessionHas('message', 'The page expired, please try again.');
})->with(['testing', 'production']);

test('the check of the email address of the API is the JSON 403 of the API, whatever the Accept header says', function (string $accept) {
    $user = User::factory()->withUserProfile()->unverified()->create();
    $url = ApiRoutes::path(ApiRoutes::USER);
    $json = $this->actingAs($user)->getJson($url)->assertForbidden();

    $response = $this->actingAs($user)->get($url, ['Accept' => $accept]);

    homeAssertApiError($response, 403, (string) $json->json('type'));
    expect($response->json())->toBe($json->json());
})->with(['any type' => '*/*', 'empty' => '', 'a browser' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8']);

/**
 * A middleware that refuses with its own answer, for the test of an exception that carries its answer
 */
final class HomeErrorProbeMiddleware
{
    public function handle(Request $request, Closure $next): never
    {
        throw new HttpResponseException(response()->json(['probe' => 'the answer of the middleware'], 409));
    }
}

test('an exception that carries its own answer keeps that answer on the API, from a middleware and from a limit', function (string $accept) {
    RateLimiter::for('home-error-probe', static fn (): Limit => Limit::perMinute(1)->by('home-error-probe')->response(
        static fn () => response()->json(['probe' => 'the answer of the limit'], 429)
    ));
    RateLimiter::clear('home-error-probe');
    Route::middleware(['api', HomeErrorProbeMiddleware::class])->get('/api/home-error-test/own-answer', static fn () => 'never');
    Route::middleware(['api', 'throttle:home-error-probe'])->get('/api/home-error-test/own-limit', static fn () => response()->json(['ok' => true]));

    $this->get('/api/home-error-test/own-answer', ['Accept' => $accept])->assertStatus(409)->assertExactJson(['probe' => 'the answer of the middleware']);
    $this->get('/api/home-error-test/own-limit', ['Accept' => $accept])->assertOk();
    $this->get('/api/home-error-test/own-limit', ['Accept' => $accept])->assertStatus(429)->assertExactJson(['probe' => 'the answer of the limit']);
})->with(['any type' => '*/*', 'JSON' => 'application/json']);

test('the JSON 429 of the API keeps Retry-After and the X-RateLimit headers', function (string $accept) {
    RateLimiter::for('home-error-limit', static fn (): Limit => Limit::perMinute(1)->by('home-error-limit'));
    RateLimiter::clear('home-error-limit');
    Route::middleware(['api', 'throttle:home-error-limit'])->get('/api/home-error-test/limit', static fn () => response()->json(['ok' => true]));

    $this->get('/api/home-error-test/limit', ['Accept' => $accept])->assertOk();
    $response = $this->get('/api/home-error-test/limit', ['Accept' => $accept]);

    homeAssertApiError($response, 429, 'tooManyRequests');
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0)
        ->and($response->headers->get('X-RateLimit-Limit'))->toBe('1')
        ->and($response->headers->get('X-RateLimit-Remaining'))->toBe('0')
        ->and((int) $response->headers->get('X-RateLimit-Reset'))->toBeGreaterThan(0);
})->with(['any type' => '*/*', 'JSON' => 'application/json']);
