<?php

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;

test('api token permissions can be updated', function () {
    $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

    $token = $user->tokens()->create([
        'name' => 'Test Token',
        'token' => Str::random(40),
        'abilities' => ['community:create', 'community:read'],
    ]);

    $this->put('/user/api-tokens/'.$token->id, [
        'name' => $token->name,
        'permissions' => [
            'community:create',
            'missing-permission',
        ],
    ]);

    expect($user->fresh()->tokens->first())
        ->can('community:create')->toBeTrue()
        ->can('community:read')->toBeFalse()
        ->can('missing-permission')->toBeFalse();
})->skip(function () {
    return ! Features::hasApiFeatures();
}, 'API support is not enabled.');
