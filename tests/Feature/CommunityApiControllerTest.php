<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\Response;
use Laravel\Jetstream\Jetstream;
use Laravel\Sanctum\Sanctum;

test('communities can be created via the API', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $input = [
        'name' => 'Test Community',
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::COMMUNITIES), $input, [
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

    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::COMMUNITIES), $input, [
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

    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::COMMUNITIES), $input, [
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

    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::COMMUNITIES), $input, [
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

    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::COMMUNITIES), $input, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the create community API rejects a name that is too long with a validation issue', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $input = [
        'name' => str_repeat('a', Constants::MAX_LENGTH_COMMUNITY_NAME + 1),
        'description' => 'Awesome community',
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::COMMUNITIES), $input, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the create community API rejects a description that is too long with a validation issue', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $input = [
        'name' => 'Test Community',
        'description' => str_repeat('a', Constants::MAX_LENGTH_COMMUNITY_DESCRIPTION + 1),
        'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::COMMUNITIES), $input, [
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

    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::COMMUNITIES), $input, [
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

    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::COMMUNITIES), $input, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
});

test('communities can be deleted via the API', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $community = $user->ownedCommunities()->latest('id')->first();

    $response = $this->json('DELETE', ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]), ['name' => $community->name], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_NO_CONTENT);

    expect($user->ownedCommunities)->toHaveCount(0);
});

test('deleting a community through the API needs the name of the community', function (array $body) {
    $owner = User::factory()->withUserProfile()->create();
    $community = Community::factory()->create(['owner_id' => $owner->id, 'name' => 'Knowledge Builders']);

    $this->actingAs($owner)->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]), $body)
        ->assertStatus(Response::HTTP_BAD_REQUEST)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value)
        ->assertJsonStructure(['errors' => ['name']]);

    expect($community->fresh())->not->toBeNull();
})->with([
    'no name' => [[]],
    'an empty name' => [['name' => '']],
    'another name' => [['name' => 'Knowledge Builder']],
    'the address in place of the name' => [['name' => 'knowledge-builders']],
    'a list in place of a text' => [['name' => ['Knowledge Builders']]],
]);

test('the name that deletes a community is its name, whatever the case and the spaces around it', function () {
    $owner = User::factory()->withUserProfile()->create();
    $community = Community::factory()->create(['owner_id' => $owner->id, 'name' => 'Knowledge Builders']);

    $this->actingAs($owner)->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]), ['name' => ' knowledge BUILDERS '])
        ->assertNoContent();

    expect($community->fresh())->toBeNull();
});

test('a person who may not delete a community gets the same refusal with and without its name', function () {
    $c = communityWithMembers();
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $c['community']->cuid]);

    foreach (['admin', 'member', 'outsider'] as $who) {
        foreach ([[], ['name' => 'Another name'], ['name' => $c['community']->name]] as $body) {
            $this->actingAs($c[$who])->deleteJson($path, $body)->assertForbidden();
        }
    }

    expect($c['community']->fresh())->not->toBeNull();
});

test('deleting a community through the API needs its own token ability, which is never in the default set', function () {
    $c = communityWithMembers();
    $community = $c['community'];
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);

    expect(Jetstream::$permissions)->toContain('communities:delete')
        ->and(Jetstream::$defaultPermissions)->not->toContain('communities:delete');

    // The abilities that change a community do not delete it, also with its name
    foreach ([['communities:write'], ['communities:read', 'communities:write', 'account:delete'], Jetstream::$defaultPermissions] as $abilities) {
        Sanctum::actingAs($c['owner'], $abilities);
        $this->deleteJson($path, ['name' => $community->name])->assertForbidden();
    }
    expect($community->fresh())->not->toBeNull();

    // The ability alone is not the name
    Sanctum::actingAs($c['owner'], ['communities:delete']);
    $this->deleteJson($path)->assertStatus(Response::HTTP_BAD_REQUEST);
    $this->deleteJson($path, ['name' => 'Another name'])->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($community->fresh())->not->toBeNull();

    // The ability is for the deletion only
    $this->patchJson($path, ['description' => 'Changed'])->assertForbidden();
    $this->getJson($path)->assertForbidden();

    $this->deleteJson($path, ['name' => $community->name])->assertNoContent();
    expect($community->fresh())->toBeNull();
});

test('a real token with only the ability to change communities does not delete one', function () {
    $c = communityWithMembers();
    $token = $c['owner']->createToken('a script that invites', ['communities:write'])->plainTextToken;

    $this->withToken($token)
        ->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $c['community']->cuid]), ['name' => $c['community']->name])
        ->assertForbidden();

    expect($c['community']->fresh())->not->toBeNull();
});

test('the delete community API forbids deleting a community owned by another user', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $community = $owner->ownedCommunities()->latest('id')->first();

    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $response = $this->json('DELETE', ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::AuthorizationIssue->value);
    expect($community->fresh())->not->toBeNull();
});

test('the delete community API returns a not found response for an unknown community', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $response = $this->json('DELETE', ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => 'does-not-exist']), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_NOT_FOUND);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::NotFound->value);
});

test('the delete community API requires authentication', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $community = $owner->ownedCommunities()->latest('id')->first();

    $response = $this->json('DELETE', ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]), [], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
});
