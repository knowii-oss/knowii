<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * The home page and the error page (#1574). The home page has no waitlist and says whether sign-up is open. The waitlist
 * route is gone. Outside the local and testing environments, the error page is the component `Error` with the HTTP
 * status, and it knows who is signed in, also on an address that matches no route.
 */

const HOME_ERROR_STATUSES = [403, 404, 500, 503];

/**
 * A route of the web group that answers with the given status, for the tests of the error page
 */
function homeErrorRoute(): string
{
    Route::middleware('web')->get('/home-error-test/{status}', static function (string $status) {
        abort((int) $status);
    });

    return '/home-error-test';
}

test('the home page says whether sign-up is open, and has no waitlist', function () {
    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Welcome')
        ->where('canLogin', true)
        ->where('canRegister', true)
        ->missing('waitlist'));
});

test('the waitlist route is gone', function () {
    $this->postJson('/api/v1/waitlist', ['email' => 'ada@example.com', 'turnstileToken' => 'token-1'])->assertNotFound();
    $this->post('/api/v1/waitlist')->assertNotFound();
});

test('the error page renders the component Error with the status, for a visitor and for a signed-in person', function () {
    $base = homeErrorRoute();
    configuredInstance();
    app()->detectEnvironment(fn () => 'production');

    foreach (HOME_ERROR_STATUSES as $status) {
        $this->get("{$base}/{$status}")->assertStatus($status)
            ->assertInertia(fn ($page) => $page->component('Error')->where('status', $status)->where('auth.user', null));
    }

    $user = User::factory()->withUserProfile()->create();
    foreach (HOME_ERROR_STATUSES as $status) {
        $this->actingAs($user)->get("{$base}/{$status}")->assertStatus($status)
            ->assertInertia(fn ($page) => $page->component('Error')->where('status', $status)->where('auth.user.cuid', $user->cuid));
    }
});

test('an address that matches no route answers 404 and the error page knows who is signed in', function () {
    configuredInstance();
    app()->detectEnvironment(fn () => 'production');
    $user = User::factory()->withUserProfile()->create();

    $this->actingAs($user)->get('/no-such-address/at-all')->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404)->where('auth.user.cuid', $user->cuid));

    // The other methods answer 404 too, not 405, and without the CSRF check (nothing changes)
    $this->actingAs($user)->post('/no-such-address')->assertNotFound();
    $this->actingAs($user)->delete('/no-such-address')->assertNotFound();
});

test('an API address that matches no route keeps the JSON 404 of the API', function () {
    $this->getJson('/api/v1/no-such-endpoint')->assertNotFound()->assertJsonPath('type', 'notFound');
    $this->postJson('/api/v1/no-such-endpoint')->assertNotFound()->assertJsonPath('type', 'notFound');
});
