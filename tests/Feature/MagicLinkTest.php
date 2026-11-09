<?php

use App\Mail\MagicLink;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => 'ada@example.com']);
});

/**
 * Ask for a link and return its token from the queued email.
 */
function askForLink(string $email = 'ada@example.com'): ?string
{
    test()->post('/login/link', ['email' => $email])->assertRedirect(route('login'));
    $token = null;
    Mail::assertQueued(MagicLink::class, function (MagicLink $mail) use (&$token) {
        $token = $mail->token;

        return true;
    });

    return $token;
}

test('a member asks for a sign-in link by email (#703)', function () {
    $this->post('/login/link', ['email' => 'ADA@example.com'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    Mail::assertQueued(MagicLink::class, fn (MagicLink $mail) => $mail->hasTo('ada@example.com'));
});

test('an unknown address gets the same answer and no email (#703)', function () {
    $this->post('/login/link', ['email' => 'nobody@example.com'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    Mail::assertNothingQueued();
});

test('the link opens a confirmation page and signs in on the button only (#703)', function () {
    $token = askForLink();

    $this->get('/login/link/'.$token)->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/MagicLink')->where('valid', true)->where('email', 'ada@example.com'));
    $this->assertGuest();

    $this->post('/login/link/'.$token)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($this->user);
});

test('a link works once (#703)', function () {
    $token = askForLink();
    $this->post('/login/link/'.$token);
    auth()->logout();

    $this->get('/login/link/'.$token)->assertInertia(fn ($page) => $page->where('valid', false));
    $this->post('/login/link/'.$token)->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a link expires after 15 minutes (#703)', function () {
    $token = askForLink();

    $this->travel(16)->minutes();

    $this->post('/login/link/'.$token)->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a wrong token does nothing (#703)', function () {
    $this->post('/login/link/'.str_repeat('x', 64))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a link never skips two-factor authentication (#703)', function () {
    $this->user->forceFill([
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_recovery_codes' => encrypt(json_encode(['one'])),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $token = askForLink();

    $this->post('/login/link/'.$token)->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('a link verifies the email address (#703)', function () {
    $this->user->forceFill(['email_verified_at' => null])->save();
    $token = askForLink();

    $this->post('/login/link/'.$token);

    expect($this->user->fresh()?->hasVerifiedEmail())->toBeTrue();
});

test('asking for links is rate limited (#703)', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login/link', ['email' => 'ada@example.com']);
    }

    $this->post('/login/link', ['email' => 'ada@example.com'])->assertStatus(429);
});

test('the email holds the link to the confirmation page (#703)', function () {
    $html = (new MagicLink('abc123'))->render();

    expect($html)->toContain('/login/link/abc123')->and($html)->toContain('15 minutes');
});
