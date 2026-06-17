<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiResourceLevel;
use App\Models\User;
use Illuminate\Http\Response;

/**
 * Create an owner with a community and a resource collection, and return the
 * API path to create a text article in that collection.
 */
function textArticlesPathFor(User $owner): string
{
    $community = $owner->ownedCommunities()->first();
    $collection = $community->communityResourceCollections()->create([
        'name' => 'Test Collection',
        'description' => 'A collection',
    ]);

    return ApiRoutes::path(ApiRoutes::COMMUNITY_RESOURCE_TEXT_ARTICLES, [
        ApiRoutes::PARAM_COMMUNITY => $community->cuid,
        ApiRoutes::PARAM_COMMUNITY_RESOURCE_COLLECTION => $collection->cuid,
    ]);
}

test('the text article API requires authentication', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
});

test('the text article API rejects a request from an unauthorized user', function () {
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

test('the text article API returns not found for an unknown community', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_RESOURCE_TEXT_ARTICLES, [
        ApiRoutes::PARAM_COMMUNITY => 'does-not-exist',
        ApiRoutes::PARAM_COMMUNITY_RESOURCE_COLLECTION => 'does-not-exist',
    ]);

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_NOT_FOUND);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::NotFound->value);
});

test('the text article API rejects a missing name with a validation issue', function () {
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

test('the text article API rejects an invalid url with a validation issue', function () {
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

test('the text article API rejects an invalid level with a validation issue', function () {
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

test('the text article API rejects video and streaming platforms with a business issue', function () {
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

test('text articles can be created via the API', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);
    $this->actingAs($owner);

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_CREATED);
})->skip('Requires faking the Browserless/Guzzle network calls (FetchUrl is final and not injectable).');
