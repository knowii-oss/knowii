<?php

use App\ApiRoutes;
use App\Contracts\Comments\CreatesComments;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\AccessGroup;
use App\Models\Event as EventModel;
use App\Models\Space;
use App\Models\SpaceGroup;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// The web routes that need no token ability (#1478): their proof is a signature, never a session
const MW_PUBLIC_WEB_ROUTES = [
    'oidc.entitlements.webhook',
    'mail.webhook',
];

beforeEach(function () {
    $this->c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->resources = Space::factory()->create([
        'community_id' => $this->c['community']->id,
        'space_group_id' => $group->id,
        'type' => KnowiiSpaceType::Resources,
    ]);
});

test('every authenticated API route names its token abilities (#689, ADR 0022)', function () {
    $missing = [];
    foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        $middleware = $route->gatherMiddleware();
        // The routes that visitors may call keep their abilities too (#1387)
        if (! str_starts_with($route->uri(), 'api/v1/') || (! in_array('auth:sanctum', $middleware, true) && ! in_array('auth.optional', $middleware, true))) {
            continue;
        }
        $hasAbility = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'ability:'));
        if (! $hasAbility) {
            $missing[] = implode('|', $route->methods()).' '.$route->uri();
        }
    }

    expect($missing)->toBe([]);
});

test('a read-only token reads but never writes (#689)', function () {
    Sanctum::actingAs($this->c['member'], ['communities:read', 'posts:read', 'resources:read']);
    $community = $this->c['community'];

    $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]))->assertOk();
    $this->patchJson(ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]), ['name' => 'Taken over'])
        ->assertForbidden();
    $this->postJson(ApiRoutes::path(ApiRoutes::COMMUNITIES), ['name' => 'Another one', 'visibility' => 'private'])->assertForbidden();
});

test('a token of the browser extension only adds resources (#689, ADR 0018)', function () {
    Sanctum::actingAs($this->c['owner'], ['resources:create']);
    $params = [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->resources->cuid];

    // Passes the ability check: the action then validates the input (no URL here)
    $this->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, $params), [])->assertStatus(400);

    $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, $params))->assertForbidden();
    $this->getJson(ApiRoutes::path(ApiRoutes::USER))->assertForbidden();
});

test('a token without user:write cannot change the avatar (#689)', function () {
    Sanctum::actingAs($this->c['member'], ['user:read']);

    $this->getJson(ApiRoutes::path(ApiRoutes::USER))->assertOk();
    $this->deleteJson(ApiRoutes::path(ApiRoutes::USER_AVATAR))->assertForbidden();
});

test('a token with every ability works like the session (#689)', function () {
    Sanctum::actingAs($this->c['owner'], ['*']);

    $this->patchJson(ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]), ['description' => 'By token'])
        ->assertOk();
});

test('the session of the web app is not limited by abilities (#689)', function () {
    $this->actingAs($this->c['owner'])
        ->patchJson(ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]), ['description' => 'By session'])
        ->assertOk();
});

test('the API routes that a visitor may call are these, all of them GET, limited per address (#1387, ADR 0044)', function () {
    $public = [];
    foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        $middleware = $route->gatherMiddleware();
        if (str_starts_with($route->uri(), 'api/v1/') && in_array('auth.optional', $middleware, true)) {
            expect($route->methods())->toBe(['GET', 'HEAD'])
                ->and($middleware)->toContain('throttle:public-reads')
                ->and($middleware)->not->toContain('auth:sanctum');
            $public[] = $route->uri();
        }
    }

    expect($public)->toEqualCanonicalizing([
        // The public page of a community (#1485)
        'api/v1/communities/{community}',
        'api/v1/communities/{community}/space-groups',
        'api/v1/communities/{community}/newest-posts',
        'api/v1/communities/{community}/spaces/{space}',
        'api/v1/communities/{community}/spaces/{space}/posts',
        'api/v1/communities/{community}/spaces/{space}/posts/{post}',
        'api/v1/communities/{community}/spaces/{space}/posts/{post}/comments',
        'api/v1/communities/{community}/spaces/{space}/pages',
        'api/v1/communities/{community}/spaces/{space}/pages/{page}',
        'api/v1/communities/{community}/spaces/{space}/materials',
        'api/v1/communities/{community}/spaces/{space}/resources',
        'api/v1/communities/{community}/spaces/{space}/resources/{resource}',
        'api/v1/communities/{community}/spaces/{space}/resources/{resource}/comments',
        // Events (#1513, ADR 0053)
        'api/v1/communities/{community}/events',
        'api/v1/communities/{community}/spaces/{space}/events',
        'api/v1/communities/{community}/spaces/{space}/events/{event}',
        // The comments of an event (#1516)
        'api/v1/communities/{community}/spaces/{space}/events/{event}/comments',
    ]);
});

test('the web routes that need no token ability are these (#1478, design 6.14 point 10)', function () {
    // A web route outside the API that has no sign-in, and whose proof is not a session: a signed address, or a call that
    // is not a GET and has no CSRF check (a webhook)
    $found = [];
    foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        if (str_starts_with($route->uri(), 'api/') || $route->isFallback) {
            continue;
        }
        $declared = $route->gatherMiddleware();
        $signedIn = collect($declared)->contains(fn ($m) => is_string($m) && (in_array($m, ['auth', 'auth:sanctum', 'auth.optional', 'guest', 'verified'], true) || str_starts_with($m, 'auth:')));
        if ($signedIn) {
            continue;
        }
        $middleware = app('router')->gatherRouteMiddleware($route);
        $signed = in_array('signed', $declared, true);
        $isGet = in_array('GET', $route->methods(), true);
        if ($signed || (! $isGet && ! in_array(PreventRequestForgery::class, $middleware, true))) {
            $found[(string) $route->getName()] = [$route, $declared, $middleware];
        }
    }

    expect(array_keys($found))->toEqualCanonicalizing(MW_PUBLIC_WEB_ROUTES);

    foreach ($found as $name => [$route, $declared, $middleware]) {
        expect(collect($declared)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')))->toBeTrue("{$name} has no limiter")
            ->and(collect($declared)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'ability:')))->toBeFalse("{$name} names an ability");
        if (! in_array('GET', $route->methods(), true)) {
            expect($route->methods())->toBe(['POST'])
                ->and($middleware)->not->toContain(PreventRequestForgery::class);
        }
        if (str_starts_with($name, 'mail.stop.')) {
            expect($declared)->toContain('signed');
        }
    }
});

// ---------------------------------------------------------------------------------------------------------------------
// The abilities come before the item of the route
// ---------------------------------------------------------------------------------------------------------------------

/**
 * Items of the routes with a scoped binding and an ability that the member cannot see: an event of a "groups" space
 * without the access group, a draft of another host, and a comment of that event. Each case has its address and the
 * address of an identifier that does not exist
 *
 * @return array<string, array{method: string, item: string, missing: string, body: array<string, string>}>
 */
function tabHiddenItems(): array
{
    $test = test();
    $c = $test->c;
    $group = SpaceGroup::factory()->create(['community_id' => $c['community']->id]);
    $groups = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $groups->accessGroups()->attach(AccessGroup::query()->create(['community_id' => $c['community']->id, 'name' => 'Buyers']));
    $public = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Public]);
    $forBuyers = EventModel::factory()->inSpace($groups)->hostedBy($c['owner'])->create(['title' => 'For the buyers']);
    $draft = EventModel::factory()->inSpace($public)->hostedBy($c['moderator'])->draft()->create(['title' => 'A draft']);
    $comment = app(CreatesComments::class)->create($c['owner'], $forBuyers, ['body' => 'For the buyers only']);
    $event = static fn (Space $space, string $cuid): string => ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENT, [
        ApiRoutes::PARAM_COMMUNITY => $c['community']->cuid, ApiRoutes::PARAM_SPACE => $space->cuid, ApiRoutes::PARAM_EVENT => $cuid,
    ]);
    $ofComment = static fn (string $route, string $cuid): string => ApiRoutes::path($route, [
        ApiRoutes::PARAM_COMMUNITY => $c['community']->cuid, ApiRoutes::PARAM_SPACE => $groups->cuid, ApiRoutes::PARAM_EVENT => $forBuyers->cuid, ApiRoutes::PARAM_COMMENT => $cuid,
    ]);

    return [
        'an event of a groups space' => ['method' => 'GET', 'item' => $event($groups, $forBuyers->cuid), 'missing' => $event($groups, 'nothing-here'), 'body' => []],
        'a draft of another host' => ['method' => 'GET', 'item' => $event($public, $draft->cuid), 'missing' => $event($public, 'nothing-here'), 'body' => []],
        'the reactions of a comment of an event' => ['method' => 'GET', 'item' => $ofComment(ApiRoutes::COMMUNITY_SPACE_EVENT_COMMENT_REACTIONS, $comment->cuid), 'missing' => $ofComment(ApiRoutes::COMMUNITY_SPACE_EVENT_COMMENT_REACTIONS, 'nothing-here'), 'body' => []],
        'the change of a comment of an event' => ['method' => 'PATCH', 'item' => $ofComment(ApiRoutes::COMMUNITY_SPACE_EVENT_COMMENT, $comment->cuid), 'missing' => $ofComment(ApiRoutes::COMMUNITY_SPACE_EVENT_COMMENT, 'nothing-here'), 'body' => ['body' => 'Changed']],
    ];
}

/**
 * One request of the member with a new personal access token that has these abilities
 *
 * @param  list<string>  $abilities
 * @param  array<string, string>  $body
 */
function tabWithToken(array $abilities, string $method, string $url, array $body): TestResponse
{
    $test = test();
    $token = $test->c['member']->createToken('script', $abilities)->plainTextToken;
    app('auth')->forgetGuards();

    return $test->withToken($token)->json($method, $url, $body);
}

test('a token without the ability gets the same answer for an item that its owner cannot see and for an identifier that does not exist (ADR 0022)', function () {
    foreach (tabHiddenItems() as $name => $case) {
        $ofItem = tabWithToken(['posts:read'], $case['method'], $case['item'], $case['body']);
        $ofMissing = tabWithToken(['posts:read'], $case['method'], $case['missing'], $case['body']);

        expect($ofItem->getStatusCode())->toBe(403, $name)
            ->and($ofMissing->getStatusCode())->toBe(403, $name)
            ->and($ofItem->getContent())->toBe($ofMissing->getContent(), $name);
    }
});

test('a token with the ability keeps the 404 of an item that its owner cannot see and of an identifier that does not exist (ADR 0022)', function () {
    foreach (tabHiddenItems() as $name => $case) {
        $ofItem = tabWithToken(['events:read', 'events:write'], $case['method'], $case['item'], $case['body']);
        $ofMissing = tabWithToken(['events:read', 'events:write'], $case['method'], $case['missing'], $case['body']);

        expect($ofItem->getStatusCode())->toBe(404, $name)
            ->and($ofMissing->getStatusCode())->toBe(404, $name)
            ->and($ofItem->getContent())->toBe($ofMissing->getContent(), $name);
    }
});

test('an account whose address is not verified gets the same answer with a session for an item that it cannot see and for an identifier that does not exist', function () {
    $cases = tabHiddenItems();
    $this->c['member']->forceFill(['email_verified_at' => null])->save();

    foreach ($cases as $name => $case) {
        $answers = [];
        foreach (['item', 'missing'] as $which) {
            app('auth')->forgetGuards();
            $answers[$which] = $this->actingAs($this->c['member']->fresh() ?? $this->c['member'])->json($case['method'], $case[$which], $case['body']);
        }

        expect($answers['item']->getStatusCode())->toBe($answers['missing']->getStatusCode(), $name)
            ->and($answers['item']->getStatusCode())->toBe(403, $name)
            ->and($answers['item']->getContent())->toBe($answers['missing']->getContent(), $name);
    }
});
