<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;

/*
 * A signed-in member never gets a server error for a request with a bad value on the routes of Jetstream: the API tokens
 * page answers 404 for a token id that is not a number or that is not a token of the account, and the route of the
 * profile photo answers 404, because this application has no profile photos of Jetstream (the avatar has its own routes).
 */

function apiTokensPageMember(): User
{
    return User::factory()->withUserProfile()->withPersonalCommunity()->create()->fresh();
}

/**
 * A change or a deletion of a token on the API tokens page, with the session of the member
 */
function apiTokensPageRequest(User $user, string $method, string $id, bool $json): TestResponse
{
    newBrowser();
    $address = '/user/api-tokens/'.rawurlencode($id);
    $body = $method === 'PUT' ? ['permissions' => ['user:read', 'posts:read']] : [];

    return $json ? test()->actingAs($user)->json($method, $address, $body) : test()->actingAs($user)->call($method, $address, $body);
}

test('the API tokens page answers 404 for a token id that is not a number or not a token of the account', function (string $method, string $id) {
    $user = apiTokensPageMember();
    $own = $user->createToken('mine', ['user:read'])->accessToken;
    $other = apiTokensPageMember();
    $foreign = $other->createToken('of another account', ['user:read'])->accessToken;
    $id = str_replace('{foreign}', (string) $foreign->id, $id);

    apiTokensPageRequest($user, $method, $id, json: false)->assertNotFound();
    apiTokensPageRequest($user, $method, $id, json: true)->assertNotFound();

    // No token changed
    expect(PersonalAccessToken::query()->whereKey([$own->id, $foreign->id])->count())->toBe(2)
        ->and($own->fresh()?->abilities)->toBe(['user:read'])
        ->and($foreign->fresh()?->abilities)->toBe(['user:read']);
})->with(['PUT', 'DELETE'])->with([
    'a word' => ['x'],
    'a number that is no token' => ['999999999'],
    'the token of another account' => ['{foreign}'],
    'a number that is too large for an id' => ['99999999999999999999999999'],
    'a number with a sign' => ['-1'],
    'a number with a fraction' => ['1.5'],
    'a number with an exponent' => ['1e3'],
    'a number with a space' => ['1 '],
]);

test('the API tokens page still changes and deletes a token of the account', function () {
    $user = apiTokensPageMember();
    $own = $user->createToken('mine', ['user:read'])->accessToken;

    apiTokensPageRequest($user, 'PUT', (string) $own->id, json: false)->assertStatus(303);
    expect($own->fresh()?->abilities)->toBe(['user:read', 'posts:read']);

    apiTokensPageRequest($user, 'DELETE', (string) $own->id, json: false)->assertStatus(303);
    expect($own->fresh())->toBeNull();
});

test('the route of the profile photo of Jetstream answers 404: the application has no such photo', function () {
    $user = apiTokensPageMember();

    newBrowser();
    $this->actingAs($user)->delete('/user/profile-photo')->assertNotFound();
    newBrowser();
    $this->actingAs($user)->deleteJson('/user/profile-photo')->assertNotFound();
    // A visitor gets the sign-in page, as before
    newBrowser();
    $this->delete('/user/profile-photo')->assertRedirect(route('login'));
});
