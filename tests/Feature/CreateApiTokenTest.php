<?php

use App\Models\User;
use Laravel\Jetstream\Features;

test('api tokens can be created', function () {
    $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

    $this->post('/user/api-tokens', [
        'name' => 'Test Token',
        'permissions' => [
            'community:read',
            'community:update',
        ],
    ]);

    expect($user->fresh()->tokens)->toHaveCount(1);
    expect($user->fresh()->tokens->first())
        ->name->toEqual('Test Token')
        ->can('community:delete')->toBeFalse()
        ->can('community:read')->toBeTrue();
})->skip(function () {
    return ! Features::hasApiFeatures();
}, 'API support is not enabled.');
