<?php

use App\Models\User;
use Laravel\Jetstream\Features;

test('api tokens can be created', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $this->post('/user/api-tokens', [
        'name' => 'Test Token',
        'permissions' => [
            'communities:read',
            'communities:write',
        ],
    ]);

    expect($user->fresh()->tokens)->toHaveCount(1);
    expect($user->fresh()->tokens->first())
        ->name->toEqual('Test Token')
        ->can('posts:write')->toBeFalse()
        ->can('communities:read')->toBeTrue();
})->skip(function () {
    return ! Features::hasApiFeatures();
}, 'API support is not enabled.');

test('new api tokens expire after 90 days by default (#1267)', function () {
    $this->freezeTime();
    $this->actingAs($user = User::factory()->withUserProfile()->create());

    $this->post('/user/api-tokens', ['name' => 'Script', 'permissions' => ['posts:read']])->assertSessionHasNoErrors();

    expect($user->tokens()->firstOrFail()->expires_at?->toDateTimeString())->toBe(now()->addDays(90)->toDateTimeString());
});

test('members choose the expiry of a token, or none (#1267)', function (string $choice, ?int $days) {
    $this->freezeTime();
    $this->actingAs($user = User::factory()->withUserProfile()->create());

    $this->post('/user/api-tokens', ['name' => 'Script', 'permissions' => ['posts:read'], 'expires_in' => $choice])->assertSessionHasNoErrors();

    $expiresAt = $user->tokens()->firstOrFail()->expires_at;
    expect($expiresAt?->toDateTimeString())->toBe($days === null ? null : now()->addDays($days)->toDateTimeString());
})->with([
    '30 days' => ['30', 30],
    '1 year' => ['365', 365],
    'never' => ['never', null],
]);

test('an unknown expiry is refused (#1267)', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->create());

    $this->post('/user/api-tokens', ['name' => 'Script', 'expires_in' => '1000'])->assertSessionHasErrors('expires_in');

    expect($user->tokens()->count())->toBe(0);
});

test('an expired token is refused by the API (#1267)', function () {
    $user = User::factory()->withUserProfile()->create();
    $token = $user->createToken('Script', ['user:read'], now()->addDays(30))->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/user')->assertOk();
    $this->travel(31)->days();
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/user')->assertUnauthorized();
});
