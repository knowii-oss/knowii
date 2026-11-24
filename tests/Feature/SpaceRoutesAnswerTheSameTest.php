<?php

use App\ApiRoutes;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceTreeChanged;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AccessGroup;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Visus\Cuid2\Cuid2;

/*
 * The rule of every route under a space, taken from the router (ADR 0011 point 10, ADR 0044 point 4): the routes are not
 * listed by hand, so a new route under a space follows the rule or fails here.
 *
 * 1. A visitor and a signed-in person who is not a member get the same answer (the status, the address of a redirect, the
 *    body and the headers) for a space that they cannot read and for a space that does not exist, on every method of
 *    every API route under `{space}` and on every page under `{spaceSlug}`.
 * 2. A member of the community outside the access groups of the space gets 403, before any item of the space is read.
 */

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $community = $this->c['community'];
    $staff = AccessGroup::query()->create(['community_id' => $community->id, 'name' => 'Staff']);

    // A space of the access group Staff: no member of the fixture is in it
    $this->space = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $community->id]))
        ->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Groups, 'name' => 'Staff room']);
    $this->space->accessGroups()->attach($staff);
});

/**
 * The routes of the router whose address names a space by the parameter given, with each method (HEAD aside)
 *
 * @return list<array{0: string, 1: RouteDefinition}>
 */
function spaceRoutesNaming(string $parameter): array
{
    $pairs = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_contains($route->uri(), '{'.$parameter.'}')) {
            continue;
        }
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $pairs[] = [$method, $route];
        }
    }

    return $pairs;
}

/**
 * The address of a route: the values given, and one random identifier for each other parameter
 *
 * @param  array<string, string>  $values
 */
function spaceRoutesAddress(RouteDefinition $route, array $values): string
{
    return '/'.preg_replace_callback('/\{(\w+)\??\}/', static fn (array $m): string => $values[$m[1]] ?? (new Cuid2)->toString(), $route->uri());
}

/**
 * The same random identifier for each parameter of the route other than the community and the space, so that the two
 * cases differ only by the space
 *
 * @return array<string, string>
 */
function spaceRoutesChildren(RouteDefinition $route): array
{
    preg_match_all('/\{(\w+)\??\}/', $route->uri(), $m);

    return array_fill_keys($m[1], (new Cuid2)->toString());
}

/**
 * One request in a new browser, with no limiter used: a limiter of the route must not answer in place of the route
 *
 * @param  array<string, string>  $headers
 */
function spaceRoutesAsk(?User $user, string $method, string $url, array $headers, bool $api): TestResponse
{
    newBrowser();
    cache()->store()->flush();
    if ($user !== null) {
        test()->actingAs(User::query()->findOrFail($user->id));
    }
    $server = [];
    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }
    if ($api) {
        $server['CONTENT_TYPE'] = 'application/json';
    }

    return test()->call($method, $url, [], [], [], $server, $api ? '{}' : null);
}

/**
 * What an answer tells: the status, the address of a redirect, the body (the JSON, or the Inertia page without the
 * address of the request) and the header values. The values that change at each request are left out
 *
 * @return array{status: int, location: string|null, body: mixed, headers: array<string, string>}
 */
function spaceRoutesAnswer(TestResponse $response): array
{
    $content = (string) $response->getContent();
    $body = json_decode($content, true);
    if (is_array($body)) {
        unset($body['url'], $body['props']['ziggy']['location']);
    } else {
        try {
            $body = $response->inertiaPage();
            unset($body['url'], $body['props']['ziggy']['location']);
        } catch (Throwable) {
            $body = $content;
        }
    }
    $headers = [];
    foreach ($response->headers->all() as $name => $values) {
        if (in_array($name, ['date', 'set-cookie', 'x-ratelimit-reset', 'retry-after'], true)) {
            continue;
        }
        $headers[$name] = (string) preg_replace(["/nonce-[^']+/", '/nonce="[^"]*"/'], ['nonce-N', 'nonce=N'], implode(',', $values));
    }
    ksort($headers);

    return ['status' => $response->getStatusCode(), 'location' => $response->headers->get('Location'), 'body' => $body, 'headers' => $headers];
}

/**
 * A short line for a difference: the two statuses and the parts of the answer that differ
 *
 * @param  array{status: int, location: string|null, body: mixed, headers: array<string, string>}  $hidden
 * @param  array{status: int, location: string|null, body: mixed, headers: array<string, string>}  $missing
 */
function spaceRoutesDifference(string $what, array $hidden, array $missing): string
{
    $parts = array_filter(array_keys($hidden), static fn (string $key): bool => $hidden[$key] !== $missing[$key]);

    return "{$what}: {$hidden['status']} for a space that the reader cannot read, {$missing['status']} for a space that does not exist; differs in ".implode(', ', $parts);
}

test('every route that names a space names it by the parameter that the access check reads', function () {
    $others = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (preg_match_all('#/spaces?/\{(\w+)\??\}#', $route->uri(), $m) > 0) {
            foreach ($m[1] as $name) {
                if (! in_array($name, [ApiRoutes::PARAM_SPACE, 'spaceSlug'], true)) {
                    $others[] = $route->uri();
                }
            }
        }
    }

    expect($others)->toBe([]);
});

test('every API route under a space gives the same answer for a space that the reader cannot read and for a space that does not exist', function () {
    $c = $this->c;
    $pairs = spaceRoutesNaming(ApiRoutes::PARAM_SPACE);
    $json = ['Accept' => 'application/json'];
    $differences = [];
    $notRefused = [];

    foreach ($pairs as [$method, $route]) {
        $values = [...spaceRoutesChildren($route), ApiRoutes::PARAM_COMMUNITY => $c['community']->cuid];
        $hiddenUrl = spaceRoutesAddress($route, [...$values, ApiRoutes::PARAM_SPACE => $this->space->cuid]);
        $missingUrl = spaceRoutesAddress($route, [...$values, ApiRoutes::PARAM_SPACE => (new Cuid2)->toString()]);

        foreach (['a visitor' => null, 'a person who is not a member' => $c['outsider']] as $reader => $user) {
            $hidden = spaceRoutesAnswer(spaceRoutesAsk($user, $method, $hiddenUrl, $json, true));
            $missing = spaceRoutesAnswer(spaceRoutesAsk($user, $method, $missingUrl, $json, true));
            if ($hidden !== $missing) {
                $differences[] = spaceRoutesDifference("{$method} {$route->uri()} ({$reader})", $hidden, $missing);
            }
        }

        $status = spaceRoutesAsk($c['member'], $method, $hiddenUrl, $json, true)->getStatusCode();
        if ($status !== 403) {
            $notRefused[] = "{$method} {$route->uri()}: {$status}";
        }
    }

    expect(count($pairs))->toBeGreaterThan(60, 'the routes under a space are found')
        ->and($differences)->toBe([])
        ->and($notRefused)->toBe([]);
});

test('every page under a space gives the same answer for a space that the reader cannot read and for a space that does not exist', function () {
    $c = $this->c;
    $pairs = spaceRoutesNaming('spaceSlug');
    $accepts = [
        'JSON' => ['Accept' => 'application/json'],
        'an Inertia visit' => [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ],
    ];
    $differences = [];
    $notRefused = [];

    foreach ($pairs as [$method, $route]) {
        $values = [...spaceRoutesChildren($route), 'communitySlug' => $c['community']->slug];
        $hiddenUrl = spaceRoutesAddress($route, [...$values, 'spaceSlug' => $this->space->slug]);
        $missingUrl = spaceRoutesAddress($route, [...$values, 'spaceSlug' => 'nothing-has-this-name']);

        foreach (['a visitor' => null, 'a person who is not a member' => $c['outsider']] as $reader => $user) {
            foreach ($accepts as $accept => $headers) {
                $hidden = spaceRoutesAnswer(spaceRoutesAsk($user, $method, $hiddenUrl, $headers, false));
                $missing = spaceRoutesAnswer(spaceRoutesAsk($user, $method, $missingUrl, $headers, false));
                if ($hidden !== $missing) {
                    $differences[] = spaceRoutesDifference("{$method} {$route->uri()} ({$reader}, {$accept})", $hidden, $missing);
                }
            }
        }

        foreach ($accepts as $accept => $headers) {
            $status = spaceRoutesAsk($c['member'], $method, $hiddenUrl, $headers, false)->getStatusCode();
            if ($status !== 403) {
                $notRefused[] = "{$method} {$route->uri()} ({$accept}): {$status}";
            }
        }
    }

    expect(count($pairs))->toBeGreaterThanOrEqual(7, 'the pages under a space are found')
        ->and($differences)->toBe([])
        ->and($notRefused)->toBe([]);
});
