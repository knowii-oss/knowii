<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Http\Middleware\AcceptOnlyTheSessionOnPages;
use App\Http\Middleware\AuthenticateIfPresent;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Laravel\Jetstream\Jetstream;
use Laravel\Sanctum\PersonalAccessToken;

/*
 * The pages of the application accept the session only (ADR 0022): an API token works on the API, with its abilities. On
 * every route outside the API, a request whose account comes from a token is refused with a 403, whatever the abilities of
 * the token. A request with a session works as before.
 */

/**
 * The sign-in middleware of a route, as the router gathers them: the class and its parameters (`Authenticate:sanctum`)
 *
 * @return list<string>
 */
function sessionOnlySignInMiddleware(RoutingRoute $route): array
{
    $found = [];
    foreach (app('router')->gatherRouteMiddleware($route) as $entry) {
        if (is_string($entry) && is_a(explode(':', $entry, 2)[0], AuthenticatesRequests::class, true)) {
            $found[] = $entry;
        }
    }

    return $found;
}

/**
 * Every route outside the API that has a sign-in middleware, read from the router
 *
 * @return list<RoutingRoute>
 */
function sessionOnlyRoutes(): array
{
    $routes = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/') && $route->uri() !== 'api' && sessionOnlySignInMiddleware($route) !== []) {
            $routes[] = $route;
        }
    }

    return $routes;
}

/**
 * Whether the sign-in middleware of the route reads a token: the guard of Sanctum, alone or for visitors too
 */
function sessionOnlyReadsTokens(RoutingRoute $route): bool
{
    foreach (sessionOnlySignInMiddleware($route) as $entry) {
        [$class, $guards] = [...explode(':', $entry, 2), ''];
        if ($class === AuthenticateIfPresent::class || ($class === Authenticate::class && in_array('sanctum', explode(',', $guards), true))) {
            return true;
        }
    }

    return false;
}

/**
 * The address of a route, with a value for each parameter: the community of the account, and a plain word for the rest
 */
function sessionOnlyAddress(RoutingRoute $route, User $user): string
{
    $community = $user->ownedCommunities()->firstOrFail()->slug;

    return '/'.preg_replace_callback('/\{(\w+)\??\}/', static fn (array $match): string => $match[1] === 'communitySlug' ? $community : 'x', $route->uri());
}

/**
 * A member with a personal community, and a token with these abilities
 *
 * @param  list<string>  $abilities
 * @return array{User, string, PersonalAccessToken}
 */
function sessionOnlyMemberWithToken(array $abilities): array
{
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create()->fresh();
    $token = $user->createToken('a token', $abilities);

    return [$user, $token->plainTextToken, $token->accessToken];
}

dataset('token abilities', [
    'a token with no ability' => [[]],
    'a token with one narrow ability' => [['resources:create']],
    'a token with every ability of the list' => [fn (): array => Jetstream::$permissions],
    'a token with the ability "*"' => [['*']],
]);

// ---------------------------------------------------------------------------------------------------------------------
// Every route outside the API
// ---------------------------------------------------------------------------------------------------------------------

test('a request with only a token is refused on every route outside the API that has a sign-in check', function (array $abilities) {
    [$user, $plain] = sessionOnlyMemberWithToken($abilities);
    $routes = sessionOnlyRoutes();
    // The pages, the routes of Jetstream and of Fortify, the invite routes, the email change routes
    expect(count($routes))->toBeGreaterThan(50);

    $wrong = [];
    $refused = 0;
    $asVisitor = 0;
    foreach ($routes as $route) {
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            newBrowser();
            $address = sessionOnlyAddress($route, $user);
            // call() sends the server variables only: the header of the token goes there
            $response = $this->call($method, $address, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$plain]);

            if (sessionOnlyReadsTokens($route)) {
                // The token gives an account here: the request is refused, never a success, never a redirect
                if ($response->getStatusCode() !== 403) {
                    $wrong[] = $method.' '.$address.' answers '.$response->getStatusCode().' '.$response->headers->get('Location');
                }
                $refused++;
            } else {
                // The session guard never reads a token: the answer of a visitor, the sign-in page
                if ($response->getStatusCode() !== 302 || $response->headers->get('Location') !== route('login')) {
                    $wrong[] = $method.' '.$address.' answers '.$response->getStatusCode().' '.$response->headers->get('Location');
                }
                $asVisitor++;
            }
        }
    }

    expect($wrong)->toBe([])
        ->and($refused)->toBeGreaterThan(30)
        ->and($asVisitor)->toBeGreaterThan(10);
})->with('token abilities');

test('the refusal is a JSON error for a request that expects JSON', function (array $abilities) {
    [, $plain] = sessionOnlyMemberWithToken($abilities);

    foreach (['/dashboard', '/user/profile', '/user/api-tokens', '/notifications', '/events'] as $address) {
        newBrowser();
        $this->withHeader('Authorization', 'Bearer '.$plain)->getJson($address)
            ->assertForbidden()
            ->assertJsonPath('type', KnowiiApiResponseType::AuthorizationIssue->value);
    }
})->with('token abilities');

test('the pages answer a session as before, also with a token header that is not valid or that is of another account', function () {
    [$user] = sessionOnlyMemberWithToken([]);
    [, $otherPlain] = sessionOnlyMemberWithToken(['*']);

    foreach (['Bearer this-is-not-a-token', 'Bearer '.$otherPlain] as $header) {
        newBrowser();
        $this->actingAs($user)->withHeader('Authorization', $header)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')->where('auth.user.cuid', $user->cuid));
        $this->assertAuthenticatedAs($user);
    }

    // The first-party front end calls the API with the session
    newBrowser();
    $this->actingAs($user)->withHeaders(['Referer' => config('app.url'), 'Origin' => config('app.url')])
        ->getJson(ApiRoutes::path(ApiRoutes::USER))
        ->assertOk();
});

// ---------------------------------------------------------------------------------------------------------------------
// The API tokens page
// ---------------------------------------------------------------------------------------------------------------------

test('a token cannot create, change or delete a token of the account on the API tokens page', function (array $abilities) {
    [$user, $plain, $token] = sessionOnlyMemberWithToken($abilities);
    $other = $user->createToken('another token', ['user:read'])->accessToken;
    $header = ['Authorization' => 'Bearer '.$plain];

    newBrowser();
    $this->withHeaders($header)->post('/user/api-tokens', ['name' => 'a new token', 'permissions' => ['account:delete', 'messages:read'], 'expires_in' => 'never'])->assertForbidden();
    expect(session('flash.token'))->toBeNull();

    foreach ([$token, $other] as $target) {
        newBrowser();
        $this->withHeaders($header)->put('/user/api-tokens/'.$target->id, ['permissions' => ['account:delete', 'messages:read']])->assertForbidden();
        newBrowser();
        $this->withHeaders($header)->delete('/user/api-tokens/'.$target->id)->assertForbidden();
    }

    newBrowser();
    $this->withHeaders($header)->get('/user/api-tokens')->assertForbidden();

    expect($user->tokens()->count())->toBe(2)
        ->and($token->fresh()?->abilities)->toBe($abilities)
        ->and($other->fresh()?->abilities)->toBe(['user:read']);
})->with('token abilities');

test('a session creates, changes and deletes a token on the API tokens page', function () {
    [$user, , $token] = sessionOnlyMemberWithToken(['user:read']);

    $this->actingAs($user)->post('/user/api-tokens', ['name' => 'a new token', 'permissions' => ['posts:read'], 'expires_in' => '30'])->assertRedirect();
    expect(session('flash.token'))->toBeString()
        ->and($user->tokens()->count())->toBe(2);

    $this->put('/user/api-tokens/'.$token->id, ['permissions' => ['posts:read']])->assertRedirect();
    expect($token->fresh()?->abilities)->toBe(['posts:read']);

    $this->delete('/user/api-tokens/'.$token->id)->assertRedirect();
    expect($user->tokens()->count())->toBe(1);
});

// ---------------------------------------------------------------------------------------------------------------------
// The pages that visitors may read
// ---------------------------------------------------------------------------------------------------------------------

test('a page for visitors refuses a request with a token: it is not read as a visitor, and not as the account', function (array $abilities) {
    $c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $c['community']->id]);
    $space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Public]);
    $post = Post::factory()->inSpace($space)->create(['author_id' => $c['member']->id]);
    $pages = [
        '/community/'.$c['community']->slug.'/space/'.$space->slug,
        '/community/'.$c['community']->slug.'/space/'.$space->slug.'/post/'.$post->slug,
    ];
    $plain = $c['member']->createToken('a token', $abilities)->plainTextToken;
    $unverified = User::factory()->unverified()->withUserProfile()->create();
    $plainOfUnverified = $unverified->createToken('a token', $abilities)->plainTextToken;

    foreach ($pages as $page) {
        // A visitor reads the public space, and a member with a session reads it
        newBrowser();
        $this->get($page)->assertOk();
        newBrowser();
        $this->actingAs($c['member'])->get($page)->assertOk();

        // A token: refused, for a page and for a request that expects JSON, also the token of an account whose address is
        // not verified (never the redirect to the address check)
        foreach ([$plain, $plainOfUnverified] as $token) {
            newBrowser();
            $this->withHeader('Authorization', 'Bearer '.$token)->get($page)->assertForbidden();
            newBrowser();
            $this->withHeader('Authorization', 'Bearer '.$token)->getJson($page)
                ->assertForbidden()
                ->assertJsonPath('type', KnowiiApiResponseType::AuthorizationIssue->value);
        }

        // A token that is not valid stays an error, as before: the sign-in page, never the page of a visitor
        newBrowser();
        $this->withHeader('Authorization', 'Bearer this-is-not-a-token')->get($page)->assertRedirect(route('login'));
    }

    // The page of an invite link is for visitors too
    newBrowser();
    $this->withHeader('Authorization', 'Bearer '.$plain)->get('/invite')->assertForbidden();
})->with('token abilities');

// ---------------------------------------------------------------------------------------------------------------------
// The API
// ---------------------------------------------------------------------------------------------------------------------

test('the API accepts a token with its abilities, as before', function () {
    [$user, $narrow] = sessionOnlyMemberWithToken(['user:read']);
    $none = $user->createToken('no ability', [])->plainTextToken;
    $every = $user->createToken('every ability', ['*'])->plainTextToken;

    newBrowser();
    $this->withHeader('Authorization', 'Bearer '.$narrow)->getJson(ApiRoutes::path(ApiRoutes::USER))->assertOk()->assertJsonPath('data.cuid', $user->cuid);
    newBrowser();
    $this->withHeader('Authorization', 'Bearer '.$narrow)->getJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS))->assertForbidden();
    newBrowser();
    $this->withHeader('Authorization', 'Bearer '.$none)->getJson(ApiRoutes::path(ApiRoutes::USER))->assertForbidden();
    newBrowser();
    $this->withHeader('Authorization', 'Bearer '.$every)->getJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS))->assertOk();
});

// ---------------------------------------------------------------------------------------------------------------------
// Where the check runs
// ---------------------------------------------------------------------------------------------------------------------

test('no file of the application reads the guard of the tokens by itself, except the sign-in check for visitors', function () {
    // A controller that would read `auth('sanctum')` on a route without a sign-in check would get the account of a token
    // on a page: the route walk above cannot see such a route
    $found = [];
    foreach (File::allFiles(app_path()) as $file) {
        if (preg_match('/\b(guard|auth|user|shouldUse)\(\s*[\'"]sanctum[\'"]/', $file->getContents()) === 1) {
            $found[] = $file->getRelativePathname();
        }
    }

    expect($found)->toBe(['Http/Middleware/AuthenticateIfPresent.php']);
});

test('the check is in the web group, right after the sign-in check of the route and before its other checks', function (string $method, string $uri) {
    expect(app('router')->getMiddlewareGroups()['web'])->toContain(AcceptOnlyTheSessionOnPages::class)
        ->and(app('router')->getMiddlewareGroups()['api'])->not->toContain(AcceptOnlyTheSessionOnPages::class);

    $route = app('router')->getRoutes()->match(Request::create($uri, $method));
    $middleware = array_values(array_filter(app('router')->gatherRouteMiddleware($route), 'is_string'));
    $position = static function (string $class) use ($middleware): ?int {
        foreach ($middleware as $index => $entry) {
            if (is_a(explode(':', $entry, 2)[0], $class, true)) {
                return $index;
            }
        }

        return null;
    };

    $signIn = $position(AuthenticatesRequests::class);
    $ours = $position(AcceptOnlyTheSessionOnPages::class);
    expect($signIn)->toBeInt()
        ->and($ours)->toBe($signIn + 1);
    // The limits of the route, the check of the password of the session and the check of the address come after it
    foreach ([ThrottleRequests::class, AuthenticateSession::class, EnsureEmailIsVerified::class] as $later) {
        if ($position($later) !== null) {
            expect($position($later))->toBeGreaterThan($ours);
        }
    }
})->with([
    'a page' => ['GET', '/dashboard'],
    'a page with a limit' => ['GET', '/community/x/members'],
    'a page for visitors' => ['GET', '/community/x/space/y'],
    'the join of an invite link' => ['POST', '/invite'],
    'a route of Jetstream' => ['POST', '/user/api-tokens'],
    'a route of Fortify' => ['PUT', '/user/password'],
    'a change of the address' => ['DELETE', '/user/email-change'],
    'the authorization of a live channel' => ['POST', '/broadcasting/auth'],
]);
