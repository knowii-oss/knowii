<?php

use App\ApiRoutes;
use App\Enums\KnowiiSpaceType;
use App\Models\Space;
use App\Models\SpaceGroup;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Laravel\Sanctum\Sanctum;

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
        if (! str_starts_with($route->uri(), 'api/v1/') || ! in_array('auth:sanctum', $middleware, true)) {
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
