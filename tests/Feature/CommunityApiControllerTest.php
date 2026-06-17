<?php

use App\Constants;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\User;
use Illuminate\Http\Response;

test('communities can be created via the API', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $input = [
        'name' => 'Test Community',
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $requestUrl = 'api/v1/communities';

    // TODO stop hardcoding URLs in tests
    $response = $this->json('POST', $requestUrl, $input, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_CREATED);

    expect($user->ownedCommunities)->toHaveCount(2);
    expect($user->ownedCommunities()->latest('id')->first()->name)->toEqual('Test Community');
    expect($user->ownedCommunities()->latest('id')->first()->slug)->toEqual('test-community');
    expect($user->ownedCommunities()->latest('id')->first()->description)->toEqual('Awesome community');
    expect($user->ownedCommunities()->latest('id')->first()->visibility)->toEqual(KnowiiCommunityVisibility::Public);
});

test('the create community API returns the created community in the response', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $input = [
        'name' => 'Test Community',
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $response = $this->json('POST', 'api/v1/communities', $input, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_CREATED);

    expect($response->json('type'))->toBe(KnowiiApiResponseType::Success->value);
    expect($response->json('data.name'))->toBe('Test Community');
    expect($response->json('data.slug'))->toBe('test-community');
    expect($response->json('data.description'))->toBe('Awesome community');
    expect($response->json('data.visibility'))->toBe(KnowiiCommunityVisibility::Public->value);
    expect($response->json('data.cuid'))->not->toBeNull();
});

test('the create community API rejects a missing name with a validation issue', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $input = [
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $response = $this->json('POST', 'api/v1/communities', $input, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the create community API rejects a missing visibility with a validation issue', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $input = [
        'name' => 'Test Community',
        'description' => 'Awesome community',
    ];

    $response = $this->json('POST', 'api/v1/communities', $input, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the create community API rejects an invalid visibility with a validation issue', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $input = [
        'name' => 'Test Community',
        'description' => 'Awesome community',
        'visibility' => 'foobar',
    ];

    $response = $this->json('POST', 'api/v1/communities', $input, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the create community API rejects a name that is too long with a validation issue', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $input = [
        'name' => str_repeat('a', Constants::$MAX_LENGTH_COMMUNITY_NAME + 1),
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $response = $this->json('POST', 'api/v1/communities', $input, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the create community API rejects a description that is too long with a validation issue', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $input = [
        'name' => 'Test Community',
        'description' => str_repeat('a', Constants::$MAX_LENGTH_COMMUNITY_DESCRIPTION + 1),
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $response = $this->json('POST', 'api/v1/communities', $input, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the create community API rejects a name with forbidden characters with a validation issue', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $input = [
        'name' => 'foo ##!}{',
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $response = $this->json('POST', 'api/v1/communities', $input, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the create community API requires authentication', function () {
    $input = [
        'name' => 'Test Community',
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $response = $this->json('POST', 'api/v1/communities', $input, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
});

test('communities can be deleted via the API', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $requestUrl = 'api/v1/communities/'.$user->ownedCommunities()->latest('id')->first()->cuid;

    // TODO stop hardcoding URLs in tests
    $response = $this->delete($requestUrl, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_NO_CONTENT);

    expect($user->ownedCommunities)->toHaveCount(0);
});

test('the delete community API forbids deleting a community owned by another user', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $community = $owner->ownedCommunities()->latest('id')->first();

    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $response = $this->json('DELETE', 'api/v1/communities/'.$community->cuid, [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::AuthorizationIssue->value);
    expect($community->fresh())->not->toBeNull();
});

test('the delete community API returns a not found response for an unknown community', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $response = $this->json('DELETE', 'api/v1/communities/does-not-exist', [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_NOT_FOUND);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::NotFound->value);
});

test('the delete community API requires authentication', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $community = $owner->ownedCommunities()->latest('id')->first();

    $response = $this->json('DELETE', 'api/v1/communities/'.$community->cuid, [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
});
