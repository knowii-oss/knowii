<?php

declare(strict_types=1);

use App\ApiRoutes;
use App\Constants;
use App\Http\Middleware\RequireTokenAbility;
use App\Models\User;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;

// The abilities of a token come before the limits of a route (ADR 0022): a token without the ability of a route gets 403
// and uses up no try of the limit of the account, so the account itself can still use the route

test('a token without the ability of a limited route gets 403 and leaves the tries of the account', function (string $route, string $method, string $without, string $with) {
    $user = User::factory()->withUserProfile()->create();

    Sanctum::actingAs($user, [$without]);
    // More tries than the limit of the route allows
    foreach (range(1, Constants::ACCOUNT_DELETIONS_PER_MINUTE + 2) as $try) {
        $this->json($method, ApiRoutes::path($route))->assertForbidden();
    }

    Sanctum::actingAs($user, [$with]);
    expect($this->json($method, ApiRoutes::path($route))->status())->not->toBe(429);
})->with([
    'the removal of a suppressed address (one try an hour)' => [ApiRoutes::NOTIFICATIONS_EMAIL_SUPPRESSION, 'DELETE', 'notifications:read', 'notifications:write'],
    'the deletion of the own account' => [ApiRoutes::USER, 'DELETE', 'user:read', 'account:delete'],
]);

test('the token ability comes before the limits in the priority of the middleware', function () {
    $priority = app(Kernel::class)->getMiddlewarePriority();

    expect(array_search(RequireTokenAbility::class, $priority, true))
        ->toBeGreaterThan(array_search(AuthenticatesRequests::class, $priority, true))
        ->and(array_search(RequireTokenAbility::class, $priority, true))
        ->toBeLessThan(array_search(ThrottleRequests::class, $priority, true))
        ->and(array_search(RequireTokenAbility::class, $priority, true))
        ->toBeLessThan(array_search(SubstituteBindings::class, $priority, true));
});
