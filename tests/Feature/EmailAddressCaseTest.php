<?php

use App\Actions\Users\ExportUser;
use App\ApiRoutes;
use App\Contracts\Auth\ReadsOpenIdConnectIdentities;
use App\Contracts\Users\ChangesEmailAddresses;
use App\Mail\ConfirmNewEmailAddress;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\ResetPasswordLink;
use App\Support\OpenIdIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
 * Every email address of an account is stored in lower case: the address, the pending address of a change and the copy
 * of the profile, at every write of the model. So a person signs in (the web, the API) and resets the password with
 * any casing of the address, and the export command finds the account the same way.
 */

const EMAIL_CASE_PASSWORD = 'a-Long-Password-9!';

const EMAIL_CASE_NEW_PASSWORD = 'A-new-password-of-2026!';

const EMAIL_CASE_ISSUER = 'https://auth.example.test';

const EMAIL_CASE_MIGRATION = '2026_11_23_230000_store_user_email_addresses_in_lower_case.php';

function emailCaseMember(string $email = 'Ada@Example.com'): User
{
    return User::factory()->withUserProfile()->withPersonalCommunity()->create([
        'name' => 'Ada Lovelace',
        'email' => $email,
        'email_verified_at' => now()->subDays(10),
        'password' => Hash::make(EMAIL_CASE_PASSWORD),
    ]);
}

function emailCaseRunMigration(): void
{
    $migration = require database_path('migrations/'.EMAIL_CASE_MIGRATION);
    $migration->up();
}

test('every write of the model stores the address of the account, the pending address and the copy of the profile in lower case', function () {
    $user = emailCaseMember();
    expect($user->email)->toBe('ada@example.com')
        ->and(DB::table('users')->where('id', $user->id)->value('email'))->toBe('ada@example.com')
        ->and(DB::table('user_profiles')->where('user_id', $user->id)->value('email'))->toBe('ada@example.com');

    Mail::fake();
    app(ChangesEmailAddresses::class)->request($user, 'Ada.New@Example.org');

    expect(DB::table('users')->where('id', $user->id)->value('pending_email'))->toBe('ada.new@example.org');
    Mail::assertQueued(ConfirmNewEmailAddress::class, fn (ConfirmNewEmailAddress $mail): bool => $mail->hasTo('ada.new@example.org'));
});

test('a change of the address to Ada.New@Example.org stores ada.new@example.org, also after its confirmation', function () {
    Mail::fake();
    $user = emailCaseMember();

    $this->actingAs($user)->put('/user/profile-information', ['name' => $user->name, 'email' => 'Ada.New@Example.org', 'current_password' => EMAIL_CASE_PASSWORD])
        ->assertSessionHasNoErrors();
    expect(DB::table('users')->where('id', $user->id)->value('pending_email'))->toBe('ada.new@example.org');

    $mail = Mail::queued(ConfirmNewEmailAddress::class)->last();
    expect($mail)->toBeInstanceOf(ConfirmNewEmailAddress::class);
    $this->actingAs($user)->get($mail instanceof ConfirmNewEmailAddress ? $mail->url : '')->assertRedirect();

    expect(DB::table('users')->where('id', $user->id)->value('email'))->toBe('ada.new@example.org')
        ->and(DB::table('users')->where('id', $user->id)->value('pending_email'))->toBeNull()
        ->and(DB::table('user_profiles')->where('user_id', $user->id)->value('email'))->toBe('ada.new@example.org');
});

test('the correction of an address that was never verified stores it in lower case', function () {
    Mail::fake();
    Notification::fake();
    $user = emailCaseMember('grace@example.com');
    $user->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($user)->put('/user/profile-information', ['name' => $user->name, 'email' => 'Grace.Hopper@Example.org', 'current_password' => EMAIL_CASE_PASSWORD])
        ->assertSessionHasNoErrors();

    expect(DB::table('users')->where('id', $user->id)->value('email'))->toBe('grace.hopper@example.org')
        ->and(DB::table('user_profiles')->where('user_id', $user->id)->value('email'))->toBe('grace.hopper@example.org');
});

test('the password sign-in works with any casing of the address', function (string $typed) {
    $user = emailCaseMember();

    $this->post('/login', ['email' => $typed, 'password' => EMAIL_CASE_PASSWORD])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
})->with(['ada@example.com', 'Ada@Example.com', 'ADA@EXAMPLE.COM']);

test('the password reset works with any casing of the address', function (string $typed) {
    Notification::fake();
    $user = emailCaseMember();

    $this->post('/forgot-password', ['email' => $typed])->assertSessionHasNoErrors();
    $token = '';
    Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->post('/reset-password', ['token' => $token, 'email' => $typed, 'password' => EMAIL_CASE_NEW_PASSWORD, 'password_confirmation' => EMAIL_CASE_NEW_PASSWORD])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));
    expect(Hash::check(EMAIL_CASE_NEW_PASSWORD, (string) $user->fresh()?->password))->toBeTrue();
})->with(['ada@example.com', 'Ada@Example.com', 'ADA@EXAMPLE.COM']);

test('the API sign-in works with any casing of the address', function (string $typed) {
    $user = emailCaseMember();

    $this->postJson(ApiRoutes::path(ApiRoutes::AUTH_LOGIN), ['email' => $typed, 'password' => EMAIL_CASE_PASSWORD])->assertOk();
    $this->assertAuthenticatedAs($user);
})->with(['ada@example.com', 'Ada@Example.com', 'ADA@EXAMPLE.COM']);

test('the export command finds the account with any casing of the address', function () {
    Storage::fake('local');
    emailCaseMember();

    $this->artisan('users:export', ['email' => 'ADA@Example.COM'])->expectsOutputToContain('.zip')->assertSuccessful();
    expect(Storage::disk('local')->files(ExportUser::DIRECTORY))->toHaveCount(1);
});

test('the sign-up with a provider stores the address of the provider in lower case', function () {
    config(['services.oidc' => ['issuer' => EMAIL_CASE_ISSUER, 'client_id' => 'knowii-test-client', 'client_secret' => 'a-secret-that-is-not-real', 'scopes' => 'openid profile email', 'label' => 'Example account']]);
    app()->instance(ReadsOpenIdConnectIdentities::class, new class implements ReadsOpenIdConnectIdentities
    {
        public function authorizationUrl(string $state, string $nonce, string $codeChallenge, bool $freshSignIn = false): string
        {
            return EMAIL_CASE_ISSUER.'/authorize?state='.$state;
        }

        public function identity(string $code, string $codeVerifier, string $nonce): OpenIdIdentity
        {
            return new OpenIdIdentity(EMAIL_CASE_ISSUER, 'sub-of-grace', 'Grace.Hopper@Example.com', true, 'Grace Hopper');
        }
    });

    $start = $this->get('/auth/oidc/redirect');
    $start->assertRedirect();
    parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $query);
    $this->get('/auth/oidc/callback?code=c&state='.($query['state'] ?? ''))->assertRedirect(route('dashboard'));

    $created = User::query()->sole();
    expect(DB::table('users')->where('id', $created->id)->value('email'))->toBe('grace.hopper@example.com')
        ->and(DB::table('user_profiles')->where('user_id', $created->id)->value('email'))->toBe('grace.hopper@example.com');
    $this->assertAuthenticatedAs($created);
});

test('the migration writes the stored addresses in lower case', function () {
    $user = emailCaseMember('ada@example.com');
    DB::table('users')->where('id', $user->id)->update(['email' => 'Ada@Example.com', 'pending_email' => 'Ada.New@Example.org', 'pending_email_requested_at' => now()]);
    DB::table('user_profiles')->where('user_id', $user->id)->update(['email' => 'Ada@Example.com']);

    emailCaseRunMigration();

    expect(DB::table('users')->where('id', $user->id)->value('email'))->toBe('ada@example.com')
        ->and(DB::table('users')->where('id', $user->id)->value('pending_email'))->toBe('ada.new@example.org')
        ->and(DB::table('user_profiles')->where('user_id', $user->id)->value('email'))->toBe('ada@example.com');
});

test('two rows with one address in two cases stop the migration with their cuids, and it changes nothing', function () {
    $user = emailCaseMember('ada@example.com');
    DB::table('users')->where('id', $user->id)->update(['email' => 'Ada@Example.com']);
    $other = UserProfile::query()->where('user_id', emailCaseMember('grace@example.com')->id)->sole();
    DB::table('user_profiles')->where('id', $other->id)->update(['email' => 'ADA@example.com']);
    DB::table('user_profiles')->where('user_id', $user->id)->update(['email' => 'Ada@Example.com']);
    $profile = UserProfile::query()->where('user_id', $user->id)->sole();

    expect($profile->id)->toBeLessThan($other->id)
        ->and(fn () => emailCaseRunMigration())->toThrow(RuntimeException::class, 'Some rows of user_profiles have the same email address in another case')
        ->and(fn () => emailCaseRunMigration())->toThrow(RuntimeException::class, $profile->cuid.', '.$other->cuid);

    expect(DB::table('users')->where('id', $user->id)->value('email'))->toBe('Ada@Example.com')
        ->and(DB::table('user_profiles')->where('id', $other->id)->value('email'))->toBe('ADA@example.com');
});
