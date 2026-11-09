<?php

use App\Actions\Resources\CreateTextResource;
use App\ApiRoutes;
use App\Contracts\Resources\CreatesTextResources;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiSpaceType;
use App\Models\CommunityResource;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Http\Response;

/**
 * Create a resources space in the personal community of the owner, and return the API path to add resources to it.
 */
function textArticlesPathFor(User $owner, KnowiiSpaceType $type = KnowiiSpaceType::Resources): string
{
    $community = $owner->ownedCommunities()->first();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $community->id]))
        ->create(['type' => $type]);

    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [
        ApiRoutes::PARAM_COMMUNITY => $community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
    ]);
}

test('the resources API requires authentication', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
});

test('the resources API rejects a request from an unauthorized user', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);

    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::AuthorizationIssue->value);
});

test('the resources API returns not found for an unknown community', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [
        ApiRoutes::PARAM_COMMUNITY => 'does-not-exist',
        ApiRoutes::PARAM_SPACE => 'does-not-exist',
    ]);

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_NOT_FOUND);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::NotFound->value);
});

test('the resources API rejects a missing name with a validation issue', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);
    $this->actingAs($owner);

    $response = $this->json('POST', $path, [
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the resources API rejects an invalid url with a validation issue', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);
    $this->actingAs($owner);

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'url' => 'not-a-valid-url',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the resources API rejects an invalid level with a validation issue', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);
    $this->actingAs($owner);

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => 'super-hard',
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the resources API rejects video and streaming platforms with a business issue', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);
    $this->actingAs($owner);

    $response = $this->json('POST', $path, [
        'name' => 'Some Video',
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::BusinessIssue->value);
});

test('resources can be created via the API', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);
    $this->actingAs($owner);

    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        .'<title>Some Article Title</title><meta name="description" content="A great read about testing">'
        .'</head><body><article><h1>Some Article Title</h1>'
        .'<p>This is a sufficiently long paragraph of readable content so that the parser has '
        .'something meaningful to extract. It discusses software testing and quality in general terms, '
        .'with enough words to be considered an article rather than boilerplate.</p>'
        .'</article></body></html>';

    // Fake the network calls made by the creator (resolved from the container).
    $creator = new CreateTextResource;
    $creator->setHttpHandlerStack(fakeTextResourceFetchHandler($html));
    $this->app->instance(CreatesTextResources::class, $creator);

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'description' => 'Worth reading',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_CREATED);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::Success->value);
    expect($response->json('data.name'))->toBe('Some Article');
    expect($response->json('data.description'))->toBe('Worth reading');
    expect($response->json('data.space.type'))->toBe(KnowiiSpaceType::Resources->value);
    expect($response->json('data.resource.url'))->toBe('https://example.com/some-article');
});

test('the resources API refuses a space of another community (#1213)', function () {
    // The attacker owns a personal community, so they may add resources there
    $attacker = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $victim = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $victimPath = textArticlesPathFor($victim);
    $victimSpaceCuid = explode('/', $victimPath)[5];

    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [
        ApiRoutes::PARAM_COMMUNITY => $attacker->ownedCommunities()->first()->cuid,
        ApiRoutes::PARAM_SPACE => $victimSpaceCuid,
    ]);

    $this->actingAs($attacker)->postJson($path, [
        'name' => 'Injected',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ])->assertNotFound();

    expect(CommunityResource::query()->count())->toBe(0);
});

test('the resources API refuses a space of another type with a business issue', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner, KnowiiSpaceType::Discussion);

    $response = $this->actingAs($owner)->postJson($path, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::BusinessIssue->value);
});
