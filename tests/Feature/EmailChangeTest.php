<?php

use App\Actions\Auth\MagicLinks;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Users\ExportUser;
use App\ApiRoutes;
use App\Contracts\Auth\ConsumesMagicLinks;
use App\Contracts\Users\ChangesEmailAddresses;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiEmailChangeResult;
use App\Mail\ConfirmNewEmailAddress;
use App\Mail\EmailAddressChanged;
use App\Mail\MagicLink;
use App\Models\Community;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use App\Support\Retention;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Sanctum\Sanctum;

/*
 * A change of the email address applies after the new address is confirmed (#1706, ADR 0058): the account keeps its
 * verified address until the link of the mail to the new address is opened in a session of the account.
 */

const EMAIL_CHANGE_PASSWORD = 'a-Long-Password-9!';
const EMAIL_CHANGE_OLD = 'ada@example.com';
const EMAIL_CHANGE_NEW = 'ada.new@example.com';
const EMAIL_CHANGE_OTHER = 'other.new@example.com';
const EMAIL_CHANGE_NOW = '2026-11-21 10:00:00';

const EMAIL_CHANGE_UNIQUE_MIGRATION = '2026_11_21_091000_make_user_email_addresses_unique_without_case.php';

beforeEach(function () {
    $this->travelTo(Carbon::parse(EMAIL_CHANGE_NOW));
    Mail::fake();
});

/**
 * A member with a verified address, a password and a profile, with fixed names
 */
function emailChangeMember(string $email = EMAIL_CHANGE_OLD, string $username = 'ada-lovelace', ?string $password = EMAIL_CHANGE_PASSWORD): User
{
    return User::factory()->withUserProfile()->create([
        'name' => 'Ada Lovelace',
        'username' => $username,
        'email' => $email,
        'email_verified_at' => Carbon::parse('2026-11-01 09:00:00'),
        'password' => $password === null ? null : Hash::make($password),
    ]);
}

/**
 * The profile form, as the browser sends it
 *
 * @param  array<string, mixed>  $extra
 */
function emailChangeRequest(User $user, string $email, ?string $password = EMAIL_CHANGE_PASSWORD, array $extra = []): TestResponse
{
    return test()->actingAs($user)->put('/user/profile-information', [
        'name' => $user->name,
        'email' => $email,
        ...($password === null ? [] : ['current_password' => $password]),
        ...$extra,
    ]);
}

/**
 * The link of the last mail that confirms a new address, as the person reads it
 */
function emailChangeLinkOfTheMail(): string
{
    $mail = Mail::queued(ConfirmNewEmailAddress::class)->last();
    expect($mail)->not->toBeNull();

    return $mail->url;
}

/**
 * Another browser, or another device: no session, no cookie, and nobody is signed in
 */
function emailChangeNewBrowser(): void
{
    session()->flush();
    session()->regenerate(true);
    app('auth')->forgetGuards();
}

// ---------------------------------------------------------------------------------------------------------------------
// The request
// ---------------------------------------------------------------------------------------------------------------------

test('a new address waits for its confirmation: the account keeps its verified address, and the link goes to the new address', function () {
    $ada = emailChangeMember();

    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();

    $fresh = $ada->fresh();
    expect($fresh->email)->toBe(EMAIL_CHANGE_OLD)
        ->and($fresh->email_verified_at?->toDateTimeString())->toBe('2026-11-01 09:00:00')
        ->and($fresh->profile->email)->not->toBe(EMAIL_CHANGE_NEW)
        ->and($fresh->pending_email)->toBe(EMAIL_CHANGE_NEW)
        ->and($fresh->pending_email_requested_at?->toDateTimeString())->toBe(EMAIL_CHANGE_NOW);

    Mail::assertQueued(ConfirmNewEmailAddress::class, 1);
    Mail::assertQueued(ConfirmNewEmailAddress::class, fn (ConfirmNewEmailAddress $mail) => $mail->hasTo(EMAIL_CHANGE_NEW) && ! $mail->hasTo(EMAIL_CHANGE_OLD));
    Mail::assertNotQueued(EmailAddressChanged::class);
});

test('the mail of the new address says what to do, with the button and the life of the link', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();

    $mail = Mail::queued(ConfirmNewEmailAddress::class)->last();
    $mail->assertHasSubject('Confirm your new email address');
    $mail->assertSeeInHtml('Open this link to use this email address for your account on Knowii.');
    $mail->assertSeeInHtml('Confirm your new email address');
    $mail->assertSeeInHtml('The link works for 60 minutes.');
    $mail->assertSeeInHtml(htmlspecialchars(emailChangeLinkOfTheMail()), false);
    expect($mail->minutes)->toBe(60);
});

test('the change of the address needs the current password: a wrong one changes nothing', function () {
    $ada = emailChangeMember();

    emailChangeRequest($ada, EMAIL_CHANGE_NEW, 'not-the-password')
        ->assertSessionHasErrorsIn('updateProfileInformation', ['current_password' => 'The provided password does not match your current password.']);
    emailChangeRequest($ada, EMAIL_CHANGE_NEW, null)
        ->assertSessionHasErrorsIn('updateProfileInformation', ['current_password']);

    $fresh = $ada->fresh();
    expect($fresh->email)->toBe(EMAIL_CHANGE_OLD)
        ->and($fresh->pending_email)->toBeNull()
        ->and($fresh->getRawOriginal('pending_email'))->toBeNull();
    Mail::assertNothingQueued();
});

test('a few wrong passwords a minute, then the form says to wait', function () {
    $ada = emailChangeMember();

    foreach (range(1, 6) as $try) {
        emailChangeRequest($ada, EMAIL_CHANGE_NEW, 'wrong-'.$try)->assertSessionHasErrorsIn('updateProfileInformation', ['current_password' => 'The provided password does not match your current password.']);
    }
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasErrorsIn('updateProfileInformation', ['current_password' => 'Too many tries. Please wait a minute, then try again.']);
    expect($ada->fresh()->pending_email)->toBeNull();

    $this->travel(61)->seconds();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    expect($ada->fresh()->pending_email)->toBe(EMAIL_CHANGE_NEW);
});

test('an account without a password changes the address without a password', function () {
    $ada = emailChangeMember(password: null);

    emailChangeRequest($ada, EMAIL_CHANGE_NEW, null)->assertSessionHasNoErrors();

    expect($ada->fresh()->pending_email)->toBe(EMAIL_CHANGE_NEW)
        ->and($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD);
    Mail::assertQueued(ConfirmNewEmailAddress::class, fn (ConfirmNewEmailAddress $mail) => $mail->hasTo(EMAIL_CHANGE_NEW));
});

test('the other fields of the profile need no password', function () {
    $ada = emailChangeMember();

    emailChangeRequest($ada, EMAIL_CHANGE_OLD, null, ['name' => 'Ada King', 'bio' => 'Numbers'])->assertSessionHasNoErrors();

    expect($ada->fresh()->name)->toBe('Ada King')
        ->and($ada->fresh()->profile->bio)->toBe('Numbers')
        ->and($ada->fresh()->pending_email)->toBeNull();
    Mail::assertNothingQueued();
});

test('the address of another account is refused, also in another case', function (string $address) {
    emailChangeMember(EMAIL_CHANGE_OTHER, 'charles-babbage');
    $ada = emailChangeMember();

    emailChangeRequest($ada, $address)->assertSessionHasErrorsIn('updateProfileInformation', ['email' => 'The email has already been taken.']);

    expect($ada->fresh()->pending_email)->toBeNull()
        ->and($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD);
    Mail::assertNothingQueued();
})->with([EMAIL_CHANGE_OTHER, 'Other.New@Example.com']);

test('the pending address of another account does not block a request', function () {
    $charles = emailChangeMember(EMAIL_CHANGE_OTHER, 'charles-babbage');
    emailChangeRequest($charles, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $ada = emailChangeMember();

    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();

    expect($ada->fresh()->pending_email)->toBe(EMAIL_CHANGE_NEW)
        ->and($charles->fresh()->pending_email)->toBe(EMAIL_CHANGE_NEW);
});

test('the same address in another case is no change: no password, no pending address, no mail', function () {
    Notification::fake();
    $ada = emailChangeMember();

    emailChangeRequest($ada, 'ADA@Example.com', null)->assertSessionHasNoErrors();

    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD)
        ->and($ada->fresh()->pending_email)->toBeNull();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
});

test('the format and the length of the new address are checked', function (string $address) {
    $ada = emailChangeMember();

    emailChangeRequest($ada, $address)->assertSessionHasErrorsIn('updateProfileInformation', ['email']);

    expect($ada->fresh()->pending_email)->toBeNull();
})->with(['not-an-address', str_repeat('a', 250).'@example.com']);

test('a new request replaces the pending address of before', function () {
    $ada = emailChangeMember();

    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $this->travel(5)->minutes();
    emailChangeRequest($ada->fresh(), 'ada.third@example.com')->assertSessionHasNoErrors();

    expect($ada->fresh()->pending_email)->toBe('ada.third@example.com')
        ->and($ada->fresh()->pending_email_requested_at?->toDateTimeString())->toBe('2026-11-21 10:05:00');
    Mail::assertQueued(ConfirmNewEmailAddress::class, 2);
});

// ---------------------------------------------------------------------------------------------------------------------
// The link
// ---------------------------------------------------------------------------------------------------------------------

test('the link applies the change: the new address is verified, the pending address goes, and a notice goes to the address of before', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $this->travel(10)->minutes();

    $this->actingAs($ada->fresh())->get(emailChangeLinkOfTheMail())
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('flash', ['banner' => 'Your new email address is confirmed.', 'bannerStyle' => 'success']);

    $fresh = $ada->fresh();
    expect($fresh->email)->toBe(EMAIL_CHANGE_NEW)
        ->and($fresh->email_verified_at?->toDateTimeString())->toBe('2026-11-21 10:10:00')
        ->and($fresh->pending_email)->toBeNull()
        ->and($fresh->getRawOriginal('pending_email_requested_at'))->toBeNull()
        ->and($fresh->profile->email)->toBe(EMAIL_CHANGE_NEW);

    Mail::assertQueued(EmailAddressChanged::class, 1);
    $notice = Mail::queued(EmailAddressChanged::class)->first();
    expect($notice->hasTo(EMAIL_CHANGE_OLD))->toBeTrue()
        ->and($notice->hasTo(EMAIL_CHANGE_NEW))->toBeFalse()
        ->and($notice->maskedAddress)->toBe('a***@example.com');
    $notice->assertHasSubject('The email address of your account on Knowii changed');
    $notice->assertSeeInHtml('The email address of your account on Knowii changed to a***@example.com.');
    $notice->assertSeeInHtml('If you did not make this change, contact the administrators of the site');
    $notice->assertDontSeeInHtml(EMAIL_CHANGE_NEW);

    // A second click changes nothing more
    $this->actingAs($ada->fresh())->get(emailChangeLinkOfTheMail())
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('flash', ['banner' => KnowiiEmailChangeResult::NotPending->message(), 'bannerStyle' => 'danger']);
    Mail::assertQueued(EmailAddressChanged::class, 1);
});

test('the notice names the contact address of the instance when it has one', function () {
    config(['legal.contact_email' => 'help@example.org']);
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();

    $this->actingAs($ada->fresh())->get(emailChangeLinkOfTheMail())->assertRedirect(route('profile.show'));

    Mail::queued(EmailAddressChanged::class)->first()
        ->assertSeeInHtml('If you did not make this change, contact the administrators of the site at help@example.org.');
});

test('the link of a replaced pending address changes nothing, and the new pending address stays', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $first = emailChangeLinkOfTheMail();
    emailChangeRequest($ada->fresh(), 'ada.third@example.com')->assertSessionHasNoErrors();

    $this->actingAs($ada->fresh())->get($first)
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('flash', ['banner' => 'This link does not work any more. Use the link of the last mail, or change the address again.', 'bannerStyle' => 'danger']);

    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD)
        ->and($ada->fresh()->pending_email)->toBe('ada.third@example.com');
    Mail::assertNotQueued(EmailAddressChanged::class);

    // The link of the last mail works
    $this->actingAs($ada->fresh())->get(emailChangeLinkOfTheMail())->assertRedirect(route('profile.show'));
    expect($ada->fresh()->email)->toBe('ada.third@example.com');
});

test('the link of a cancelled change changes nothing', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $this->actingAs($ada->fresh())->delete('/user/email-change')->assertRedirect();

    $this->actingAs($ada->fresh())->get(emailChangeLinkOfTheMail())
        ->assertSessionHas('flash', ['banner' => KnowiiEmailChangeResult::NotPending->message(), 'bannerStyle' => 'danger']);

    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD);
});

test('the link opened by another account answers 403 and changes nothing', function () {
    $ada = emailChangeMember();
    $charles = emailChangeMember(EMAIL_CHANGE_OTHER, 'charles-babbage');
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $link = emailChangeLinkOfTheMail();

    emailChangeNewBrowser();
    $this->actingAs($charles)->get($link)->assertForbidden();

    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD)
        ->and($ada->fresh()->pending_email)->toBe(EMAIL_CHANGE_NEW)
        ->and($charles->fresh()->email)->toBe(EMAIL_CHANGE_OTHER);
});

test('the link in another browser asks for a sign-in of the account first, then applies the change', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $link = emailChangeLinkOfTheMail();

    emailChangeNewBrowser();
    $this->get($link)->assertRedirect(route('login'));
    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD);

    // The sign-in uses the address of now: the new one is not the address of the account yet
    $this->post('/login', ['email' => EMAIL_CHANGE_NEW, 'password' => EMAIL_CHANGE_PASSWORD])->assertSessionHasErrors();
    $this->post('/login', ['email' => EMAIL_CHANGE_OLD, 'password' => EMAIL_CHANGE_PASSWORD])->assertRedirect($link);
    $this->get($link)->assertRedirect(route('profile.show'));

    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_NEW);
});

test('the link lives 60 minutes', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $link = emailChangeLinkOfTheMail();

    $this->travel(61)->minutes();
    $this->actingAs($ada->fresh())->get($link)->assertForbidden();
    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD)
        ->and($ada->fresh()->pending_email)->toBe(EMAIL_CHANGE_NEW);

    // "Send the link again" gives a new link, which works
    $this->actingAs($ada->fresh())->post('/user/email-change/resend')->assertRedirect();
    $this->actingAs($ada->fresh())->get(emailChangeLinkOfTheMail())->assertRedirect(route('profile.show'));
    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_NEW);
});

test('a link with a changed address hash or a changed account fails its signature', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $link = emailChangeLinkOfTheMail();
    $hash = Str::of($link)->after('/user/email-change/'.$ada->cuid.'/')->before('?')->toString();

    $this->actingAs($ada->fresh())->get(str_replace($hash, hash('sha256', 'something else'), $link))->assertForbidden();
    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD);
});

test('a link signed for the account with another hash changes nothing', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $signed = URL::temporarySignedRoute('email-change.confirm', now()->addMinutes(60), ['userCuid' => $ada->cuid, 'hash' => hash_hmac('sha256', 'pending-email:'.EMAIL_CHANGE_OTHER, config()->string('app.key'))]);

    $this->actingAs($ada->fresh())->get($signed)
        ->assertSessionHas('flash', ['banner' => KnowiiEmailChangeResult::NotPending->message(), 'bannerStyle' => 'danger']);
    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD)
        ->and($ada->fresh()->pending_email)->toBe(EMAIL_CHANGE_NEW);
});

test('an address that another account took in between is not applied, and the pending address goes', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $link = emailChangeLinkOfTheMail();
    // Another person signs up with the address before the link is opened
    emailChangeMember('Ada.New@example.com', 'someone-else');

    $this->actingAs($ada->fresh())->get($link)
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('flash', ['banner' => 'Another account uses this email address now. The change is cancelled.', 'bannerStyle' => 'danger']);

    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD)
        ->and($ada->fresh()->getRawOriginal('pending_email'))->toBeNull();
    Mail::assertNotQueued(EmailAddressChanged::class);
});

test('the confirmation works on the row of the account read again: a stale model of the caller changes nothing of it', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $link = emailChangeLinkOfTheMail();
    $hash = Str::of($link)->after('/user/email-change/'.$ada->cuid.'/')->before('?')->toString();
    $stale = User::query()->findOrFail($ada->id);
    // The change is cancelled in another request, after the caller read the account
    DB::table('users')->where('id', $ada->id)->update(['pending_email' => null, 'pending_email_requested_at' => null]);

    expect(app(ChangesEmailAddresses::class)->confirm($stale, $hash))->toBe(KnowiiEmailChangeResult::NotPending)
        ->and($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD)
        ->and($stale->pending_email)->toBeNull();
});

test('the link needs a signed-in session: without one it asks for the sign-in', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $link = emailChangeLinkOfTheMail();

    emailChangeNewBrowser();
    $this->getJson($link)->assertUnauthorized();
    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD);
});

// ---------------------------------------------------------------------------------------------------------------------
// Send again, cancel
// ---------------------------------------------------------------------------------------------------------------------

test('"Send the link again" sends a new link to the pending address, without a password, and keeps the time of the request', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $this->travel(30)->minutes();

    $this->actingAs($ada->fresh())->post('/user/email-change/resend')
        ->assertRedirect()
        ->assertSessionHas('flash', ['banner' => 'We sent the link again.', 'bannerStyle' => 'success']);

    Mail::assertQueued(ConfirmNewEmailAddress::class, 2);
    Mail::assertQueued(ConfirmNewEmailAddress::class, fn (ConfirmNewEmailAddress $mail) => $mail->hasTo(EMAIL_CHANGE_NEW));
    expect($ada->fresh()->pending_email_requested_at?->toDateTimeString())->toBe(EMAIL_CHANGE_NOW);
});

test('"Send the link again" without a change that waits sends nothing', function () {
    $ada = emailChangeMember();

    $this->actingAs($ada)->post('/user/email-change/resend')
        ->assertRedirect()
        ->assertSessionHas('flash', ['banner' => 'No change of the email address waits for its confirmation.', 'bannerStyle' => 'danger']);
    Mail::assertNothingQueued();
});

test('"Cancel the change" removes the pending address, and the account keeps its address', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();

    $this->actingAs($ada->fresh())->delete('/user/email-change')
        ->assertRedirect()
        ->assertSessionHas('flash', ['banner' => 'The change of the email address is cancelled.', 'bannerStyle' => 'success']);

    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD)
        ->and($ada->fresh()->getRawOriginal('pending_email'))->toBeNull()
        ->and($ada->fresh()->getRawOriginal('pending_email_requested_at'))->toBeNull();
});

test('the buttons and the link need a signed-in account', function () {
    $this->post('/user/email-change/resend')->assertRedirect(route('login'));
    $this->delete('/user/email-change')->assertRedirect(route('login'));
});

test('the API sends the link again and cancels the change, with user:write', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    emailChangeNewBrowser();
    Sanctum::actingAs($ada->fresh(), ['user:write']);

    $this->postJson('/api/v1/'.ApiRoutes::USER_EMAIL_CHANGE_RESEND)
        ->assertOk()
        ->assertJsonPath('data.pending_email', EMAIL_CHANGE_NEW)
        ->assertJsonPath('data.email', EMAIL_CHANGE_OLD);
    Mail::assertQueued(ConfirmNewEmailAddress::class, 2);

    $this->deleteJson('/api/v1/'.ApiRoutes::USER_EMAIL_CHANGE)
        ->assertOk()
        ->assertJsonPath('data.pending_email', null)
        ->assertJsonPath('data.email', EMAIL_CHANGE_OLD);
    expect($ada->fresh()->getRawOriginal('pending_email'))->toBeNull();

    // Nothing waits any more: "send again" is refused, "cancel" changes nothing
    $this->postJson('/api/v1/'.ApiRoutes::USER_EMAIL_CHANGE_RESEND)->assertStatus(400)
        ->assertJsonPath('errors.pending_email.0', 'No change of the email address waits for its confirmation.');
    $this->deleteJson('/api/v1/'.ApiRoutes::USER_EMAIL_CHANGE)->assertOk();
    Mail::assertQueued(ConfirmNewEmailAddress::class, 2);
});

test('the API routes need the ability user:write', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    emailChangeNewBrowser();
    Sanctum::actingAs($ada->fresh(), ['user:read']);

    $this->postJson('/api/v1/'.ApiRoutes::USER_EMAIL_CHANGE_RESEND)->assertForbidden();
    $this->deleteJson('/api/v1/'.ApiRoutes::USER_EMAIL_CHANGE)->assertForbidden();
    expect($ada->fresh()->pending_email)->toBe(EMAIL_CHANGE_NEW);
});

test('"Send the link again" and "Cancel the change" are limited per minute, the web and the API together, with the change in the profile', function () {
    $ada = emailChangeMember();
    // The request counts too: 1 of the 6 of the minute
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();

    foreach (range(1, 2) as $try) {
        $this->actingAs($ada->fresh())->post('/user/email-change/resend')->assertRedirect();
    }
    emailChangeNewBrowser();
    Sanctum::actingAs($ada->fresh(), ['user:write']);
    foreach (range(1, 3) as $try) {
        $this->postJson('/api/v1/'.ApiRoutes::USER_EMAIL_CHANGE_RESEND)->assertOk();
    }
    $this->postJson('/api/v1/'.ApiRoutes::USER_EMAIL_CHANGE_RESEND)->assertStatus(429);
    $this->deleteJson('/api/v1/'.ApiRoutes::USER_EMAIL_CHANGE)->assertStatus(429);
    expect($ada->fresh()->pending_email)->toBe(EMAIL_CHANGE_NEW);
});

// ---------------------------------------------------------------------------------------------------------------------
// The 7 days
// ---------------------------------------------------------------------------------------------------------------------

test('a pending address waits 7 days at most: then it does not count, and retention:prune removes it', function () {
    // The prune also reads the export files of the local disk
    Storage::fake('local');
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $younger = emailChangeMember('young@example.com', 'young-one');
    emailChangeRequest($younger, 'young.new@example.com')->assertSessionHasNoErrors();
    $this->travelBack();
    $this->travelTo(Carbon::parse('2026-11-25 10:00:00'));
    emailChangeRequest($younger->fresh(), 'young.new@example.com')->assertSessionHasNoErrors();

    $this->travelTo(Carbon::parse('2026-11-28 09:59:59'));
    expect($ada->fresh()->pending_email)->toBe(EMAIL_CHANGE_NEW);

    $this->travelTo(Carbon::parse('2026-11-28 10:00:00'));
    expect($ada->fresh()->pending_email)->toBeNull()
        ->and($younger->fresh()->pending_email)->toBe('young.new@example.com');
    $this->actingAs($ada->fresh())->post('/user/email-change/resend')
        ->assertSessionHas('flash', ['banner' => 'No change of the email address waits for its confirmation.', 'bannerStyle' => 'danger']);

    $this->artisan('retention:prune', ['--dry-run' => true])->assertSuccessful();
    expect($ada->fresh()->getRawOriginal('pending_email'))->toBe(EMAIL_CHANGE_NEW);

    $this->artisan('retention:prune')->assertSuccessful();
    expect($ada->fresh()->getRawOriginal('pending_email'))->toBeNull()
        ->and($ada->fresh()->getRawOriginal('pending_email_requested_at'))->toBeNull()
        ->and($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD)
        ->and($younger->fresh()->getRawOriginal('pending_email'))->toBe('young.new@example.com');
});

test('the link of a pending address older than 7 days changes nothing', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();
    $link = emailChangeLinkOfTheMail();
    $hash = Str::of($link)->after('/user/email-change/'.$ada->cuid.'/')->before('?')->toString();

    $this->travelTo(Carbon::parse('2026-11-28 10:00:00'));
    // A link that is still signed: the life of the link is a configuration value
    $signed = URL::temporarySignedRoute('email-change.confirm', now()->addMinutes(60), ['userCuid' => $ada->cuid, 'hash' => $hash]);
    $this->actingAs($ada->fresh())->get($signed)
        ->assertSessionHas('flash', ['banner' => KnowiiEmailChangeResult::NotPending->message(), 'bannerStyle' => 'danger']);

    expect($ada->fresh()->email)->toBe(EMAIL_CHANGE_OLD);
});

// ---------------------------------------------------------------------------------------------------------------------
// Who sees the pending address
// ---------------------------------------------------------------------------------------------------------------------

test('the own export holds the pending address and the time of the request', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();

    $head = (new ReflectionMethod(ExportUser::class, 'accountHead'))->invoke(app(ExportUser::class), $ada->fresh());

    expect($head['account']['pending_email'])->toBe(EMAIL_CHANGE_NEW)
        ->and($head['account']['pending_email_requested_at'])->toBe(Carbon::parse(EMAIL_CHANGE_NOW)->toIso8601String())
        ->and($head['account']['email'])->toBe(EMAIL_CHANGE_OLD);
});

test('the member sees the pending address in the account of the page and in the own resource; no other person sees it', function () {
    $owner = emailChangeMember('owner@example.com', 'owner-one');
    $community = Community::factory()->create(['owner_id' => $owner->id, 'name' => 'Harbour Of Changes', 'slug' => 'harbour-of-changes']);
    $ada = emailChangeMember();
    $charles = emailChangeMember(EMAIL_CHANGE_OTHER, 'charles-babbage');
    $community->users()->attach([$ada->id => ['role' => KnowiiCommunityMemberRole::Member], $charles->id => ['role' => KnowiiCommunityMemberRole::Member]]);
    $group = SpaceGroup::factory()->create(['community_id' => $community->id, 'name' => 'Main']);
    $space = Space::factory()->inGroup($group)->create(['name' => 'Talk', 'slug' => 'talk']);
    Post::factory()->inSpace($space)->create(['author_id' => $ada->id, 'title' => 'A post of Ada', 'slug' => 'a-post-of-ada', 'body' => 'The text']);
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();

    // The member: the account of the page and the own resource
    $this->actingAs($ada->fresh())->get('/user/profile')->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.user.pending_email', EMAIL_CHANGE_NEW)->where('accountHasPassword', true));
    $this->actingAs($ada->fresh())->getJson('/api/v1/'.ApiRoutes::USER)->assertOk()
        ->assertJsonPath('data.pending_email', EMAIL_CHANGE_NEW)
        ->assertJsonPath('data.pending_email_requested_at', Carbon::parse(EMAIL_CHANGE_NOW)->toJSON());

    // Another member reads the member list, the profile, the post with its author and the space
    emailChangeNewBrowser();
    $this->actingAs($charles);
    $reads = [
        $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]))->assertOk()->getContent(),
        $this->get('/community/harbour-of-changes/member/ada-lovelace')->assertOk()->getContent(),
        $this->get('/community/harbour-of-changes/space/talk/post/a-post-of-ada')->assertOk()->getContent(),
        $this->get('/community/harbour-of-changes/space/talk')->assertOk()->getContent(),
    ];
    // The account of the page of the other member holds the own empty change of that member, and nothing else of it
    $ownEmptyChange = ['"pending_email":null', '"pending_email_requested_at":null', '&quot;pending_email&quot;:null', '&quot;pending_email_requested_at&quot;:null'];
    foreach ($reads as $content) {
        expect($content)->not->toContain(EMAIL_CHANGE_NEW)
            ->and(str_replace($ownEmptyChange, '', (string) $content))->not->toContain('pending_email');
    }
    expect($this->get('/community/harbour-of-changes/space/talk')->assertOk()->viewData('page')['props']['auth']['user'])
        ->toMatchArray(['pending_email' => null, 'pending_email_requested_at' => null]);
});

// ---------------------------------------------------------------------------------------------------------------------
// An address that was never verified
// ---------------------------------------------------------------------------------------------------------------------

test('an address that was never verified is corrected at once, and the address check goes to the new address', function () {
    Notification::fake();
    $grace = User::factory()->unverified()->withUserProfile()->create([
        'name' => 'Grace Hopper', 'username' => 'grace-hopper', 'email' => 'grace@exmaple.com', 'password' => Hash::make(EMAIL_CHANGE_PASSWORD),
    ]);

    emailChangeRequest($grace, 'grace@example.com', EMAIL_CHANGE_PASSWORD, ['name' => 'Another Name'])->assertSessionHasNoErrors();

    $fresh = $grace->fresh();
    expect($fresh->email)->toBe('grace@example.com')
        ->and($fresh->email_verified_at)->toBeNull()
        ->and($fresh->pending_email)->toBeNull()
        ->and($fresh->profile->email)->toBe('grace@example.com')
        // The other fields wait for the address check
        ->and($fresh->name)->toBe('Grace Hopper');
    Notification::assertSentTo($fresh, VerifyEmailAddress::class);
    Notification::assertCount(1);
    Mail::assertNotQueued(ConfirmNewEmailAddress::class);
    Mail::assertNotQueued(EmailAddressChanged::class);
});

test('the correction of an address that was never verified needs the password too, and the address of another account is refused', function () {
    Notification::fake();
    emailChangeMember(EMAIL_CHANGE_OTHER, 'charles-babbage');
    $grace = User::factory()->unverified()->withUserProfile()->create([
        'name' => 'Grace Hopper', 'username' => 'grace-hopper', 'email' => 'grace@exmaple.com', 'password' => Hash::make(EMAIL_CHANGE_PASSWORD),
    ]);

    emailChangeRequest($grace, 'grace@example.com', 'not-the-password')->assertSessionHasErrorsIn('updateProfileInformation', ['current_password']);
    emailChangeRequest($grace, EMAIL_CHANGE_OTHER)->assertSessionHasErrorsIn('updateProfileInformation', ['email']);

    expect($grace->fresh()->email)->toBe('grace@exmaple.com');
    Notification::assertNothingSent();
});

test('an account whose address was never verified keeps its other fields until the address check', function () {
    Notification::fake();
    $grace = User::factory()->unverified()->withUserProfile()->create([
        'name' => 'Grace Hopper', 'username' => 'grace-hopper', 'email' => 'grace@example.com',
    ]);

    emailChangeRequest($grace, 'grace@example.com', null, ['name' => 'Another Name', 'bio' => 'Ships'])->assertSessionHasNoErrors();

    expect($grace->fresh()->name)->toBe('Grace Hopper')
        ->and($grace->fresh()->profile->bio)->toBeNull();
    Notification::assertNothingSent();
});

test('the columns of the pending address exist, with the length of the address', function () {
    expect(Schema::hasColumns('users', ['pending_email', 'pending_email_requested_at']))->toBeTrue()
        ->and(Schema::getColumnType('users', 'pending_email'))->toBe('varchar');
});

// ---------------------------------------------------------------------------------------------------------------------
// The correction of an address that was never verified, on the row read again and locked
// ---------------------------------------------------------------------------------------------------------------------

/**
 * An account whose address was never verified, with a password and a profile, with fixed names
 */
function emailChangeNeverVerified(string $email = 'grace@example.com'): User
{
    return User::factory()->unverified()->withUserProfile()->create([
        'name' => 'Grace Hopper', 'username' => 'grace-hopper', 'email' => $email, 'password' => Hash::make(EMAIL_CHANGE_PASSWORD),
    ]);
}

test('a correction with a model of before changes nothing when the address check verified the row in between', function () {
    Notification::fake();
    $grace = emailChangeNeverVerified();
    // The model of the request of the correction, read before the address check
    $stale = User::query()->findOrFail($grace->id);
    expect(User::query()->findOrFail($grace->id)->markEmailAsVerified())->toBeTrue();

    app(UpdatesUserProfileInformation::class)->update($stale, ['name' => 'Grace Hopper', 'email' => 'other@example.com', 'current_password' => EMAIL_CHANGE_PASSWORD]);

    $row = $grace->fresh();
    expect($row->email)->toBe('grace@example.com')
        ->and($row->email_verified_at?->toDateTimeString())->toBe(EMAIL_CHANGE_NOW)
        ->and($row->pending_email)->toBeNull()
        ->and($row->profile->email)->toBe('grace@example.com')
        // The model of the caller holds the stored values
        ->and($stale->email)->toBe('grace@example.com')
        ->and($stale->hasVerifiedEmail())->toBeTrue();
    Notification::assertNothingSent();
    Mail::assertNothingQueued();
});

test('a correction checks the password against the row read again', function () {
    Notification::fake();
    $grace = emailChangeNeverVerified();
    $stale = User::query()->findOrFail($grace->id);
    DB::table('users')->where('id', $grace->id)->update(['password' => Hash::make('another-password-of-2026')]);

    expect(fn () => app(UpdatesUserProfileInformation::class)->update($stale, ['name' => 'Grace Hopper', 'email' => 'other@example.com', 'current_password' => EMAIL_CHANGE_PASSWORD]))
        ->toThrow(ValidationException::class, 'The provided password does not match your current password.');

    expect($grace->fresh()->email)->toBe('grace@example.com');
    Notification::assertNothingSent();
});

test('the link of the address check of the address of before verifies nothing after a correction: 403', function () {
    $grace = emailChangeNeverVerified();
    // The model of the request of the link, read before the correction
    $stale = User::query()->findOrFail($grace->id);
    $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $grace->id, 'hash' => sha1('grace@example.com')]);
    app(UpdatesUserProfileInformation::class)->update($grace->fresh(), ['name' => 'Grace Hopper', 'email' => 'grace.new@example.com', 'current_password' => EMAIL_CHANGE_PASSWORD]);
    expect($grace->fresh()->email)->toBe('grace.new@example.com');

    $this->actingAs($stale)->get($link)->assertForbidden();

    $row = $grace->fresh();
    expect($row->email)->toBe('grace.new@example.com')
        ->and($row->email_verified_at)->toBeNull();
});

test('the address check writes only while the row has the address of the model and no verified address', function () {
    $grace = emailChangeNeverVerified();
    $stale = User::query()->findOrFail($grace->id);
    DB::table('users')->where('id', $grace->id)->update(['email' => 'grace.new@example.com']);

    // Another address on the row: nothing is written, and the model does not change
    expect($stale->markEmailAsVerified())->toBeFalse()
        ->and($stale->email_verified_at)->toBeNull()
        ->and($grace->fresh()->email_verified_at)->toBeNull();

    // The address of the row: written once
    $fresh = $grace->fresh();
    expect($fresh->markEmailAsVerified())->toBeTrue()
        ->and($fresh->email_verified_at?->toDateTimeString())->toBe(EMAIL_CHANGE_NOW)
        ->and($fresh->isDirty())->toBeFalse()
        ->and($grace->fresh()->email_verified_at?->toDateTimeString())->toBe(EMAIL_CHANGE_NOW);

    // Verified already: nothing more is written
    $this->travel(5)->minutes();
    expect($fresh->markEmailAsVerified())->toBeFalse()
        ->and($grace->fresh()->email_verified_at?->toDateTimeString())->toBe(EMAIL_CHANGE_NOW);
});

test('the link of the address check still verifies the address of the account', function () {
    $grace = emailChangeNeverVerified();
    $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $grace->id, 'hash' => sha1('grace@example.com')]);

    $this->actingAs($grace->fresh())->get($link)->assertRedirect();

    expect($grace->fresh()->email_verified_at?->toDateTimeString())->toBe(EMAIL_CHANGE_NOW);
});

test('a sign-in link of the address of before gives no account after a correction: the new address is not verified, and nobody is signed in', function () {
    Notification::fake();
    $grace = emailChangeNeverVerified();
    $this->post('/login/link', ['email' => 'grace@example.com'])->assertRedirect(route('login'));
    $mail = Mail::queued(MagicLink::class)->last();
    expect($mail)->not->toBeNull();
    $token = $mail->token;

    emailChangeRequest($grace, 'grace.new@example.com')->assertSessionHasNoErrors();
    expect($grace->fresh()->email)->toBe('grace.new@example.com');

    emailChangeNewBrowser();
    expect(app(ConsumesMagicLinks::class)->peek($token))->toBeNull();
    $this->get('/login/link/'.$token)->assertOk()->assertInertia(fn ($page) => $page->where('valid', false)->where('email', null));
    $this->post('/login/link/'.$token)->assertRedirect(route('login'))->assertSessionHas('status', 'This sign-in link expired or was already used. Ask for a new one.');
    $this->assertGuest();

    $row = $grace->fresh();
    expect($row->email)->toBe('grace.new@example.com')
        ->and($row->email_verified_at)->toBeNull()
        ->and(Hash::check(EMAIL_CHANGE_PASSWORD, (string) $row->password))->toBeTrue();
});

test('the entry of a sign-in link holds the account and an HMAC of the address with the key of the application, never the address', function () {
    $grace = emailChangeNeverVerified();
    $this->post('/login/link', ['email' => 'grace@example.com'])->assertRedirect(route('login'));
    $mail = Mail::queued(MagicLink::class)->last();
    expect($mail)->not->toBeNull();

    $entry = Cache::get('magic-link:'.hash('sha256', $mail->token));
    expect($entry)->toBe(['user' => $grace->id, 'address' => hash_hmac('sha256', 'grace@example.com', config()->string('app.key'))])
        ->and($entry['address'])->not->toBe(hash('sha256', 'grace@example.com'))
        ->and((string) json_encode($entry))->not->toContain('grace@example.com');
    expect(app(ConsumesMagicLinks::class)->peek($mail->token)?->is($grace))->toBeTrue();

    // An entry that holds a plain hash of the address, or only the id of the account, gives no account
    $plain = str_repeat('p', 64);
    Cache::put('magic-link:'.hash('sha256', $plain), ['user' => $grace->id, 'address' => hash('sha256', 'grace@example.com')], now()->addMinutes(MagicLinks::VALIDITY_MINUTES));
    $idOnly = str_repeat('k', 64);
    Cache::put('magic-link:'.hash('sha256', $idOnly), $grace->id, now()->addMinutes(MagicLinks::VALIDITY_MINUTES));
    expect(app(ConsumesMagicLinks::class)->peek($plain))->toBeNull()
        ->and(app(ConsumesMagicLinks::class)->peek($idOnly))->toBeNull();
});

test('a correction that another account wins at the write gets the error of a taken address, never an error page', function () {
    Notification::fake();
    $grace = emailChangeNeverVerified();
    // Another account takes the address between the check and the write
    User::updating(static function (User $user) use ($grace): void {
        if ($user->is($grace) && $user->isDirty('email')) {
            DB::table('users')->insert(['cuid' => 'cuid-of-the-other-account', 'username' => 'the-other-account', 'name' => 'Other', 'email' => 'GRACE.NEW@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    emailChangeRequest($grace, 'grace.new@example.com')->assertRedirect()->assertSessionHasErrorsIn('updateProfileInformation', ['email']);

    expect($grace->fresh()->email)->toBe('grace@example.com');
    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------------------------------------------------
// One address per account, without regard to the case
// ---------------------------------------------------------------------------------------------------------------------

test('the sign-up refuses an address that another account has in another case', function () {
    emailChangeMember();

    $this->post('/register', ['name' => 'Grace Hopper', 'email' => 'ADA@Example.com', 'password' => EMAIL_CHANGE_PASSWORD, 'password_confirmation' => EMAIL_CHANGE_PASSWORD, 'terms' => true])
        ->assertSessionHasErrors(['email' => __('validation.unique', ['attribute' => 'email'])]);

    expect(User::query()->count())->toBe(1);
    $this->assertGuest();
});

test('a sign-up that another account wins at the write gets the error of a taken address, never an error page', function () {
    User::creating(static function (User $user): void {
        if ($user->email === 'grace@example.com') {
            DB::table('users')->insert(['cuid' => 'cuid-of-the-other-account', 'username' => 'the-other-account', 'name' => 'Other', 'email' => 'Grace@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    $this->post('/register', ['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'password' => EMAIL_CHANGE_PASSWORD, 'password_confirmation' => EMAIL_CHANGE_PASSWORD, 'terms' => true])
        ->assertRedirect()
        ->assertSessionHasErrors(['email' => __('validation.unique', ['attribute' => 'email'])]);

    $this->assertGuest();
});

test('a sign-up that another account wins at the write on another value gets the error of a sign-up that did not work, never the error of a taken address', function () {
    // Another account takes the username of the new account between its choice and the write
    User::creating(static function (User $user): void {
        if ($user->email === 'grace@example.com') {
            DB::table('users')->insert(['cuid' => 'cuid-of-the-other-account', 'username' => $user->username, 'name' => 'Other', 'email' => 'other@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    $this->post('/register', ['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'password' => EMAIL_CHANGE_PASSWORD, 'password_confirmation' => EMAIL_CHANGE_PASSWORD, 'terms' => true])
        ->assertRedirect()
        ->assertSessionHasErrors(['email' => CreateNewUser::NOT_CREATED_MESSAGE]);

    expect(session('errors')->get('email'))->not->toContain(__('validation.unique', ['attribute' => 'email']))
        ->and(User::query()->where('email', 'grace@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

test('the database refuses a second account with the same address in another case', function () {
    emailChangeMember();

    expect(fn () => DB::table('users')->insert(['cuid' => 'cuid-of-the-other-account', 'username' => 'the-other-account', 'name' => 'Other', 'email' => 'Ada@Example.COM', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the migration of the unique address stops, and changes nothing, while two accounts have one address in two cases', function () {
    $migration = require database_path('migrations/'.EMAIL_CHANGE_UNIQUE_MIGRATION);
    $migration->down();
    $ada = emailChangeMember();
    $twin = emailChangeMember('ADA@example.com', 'ada-twin');

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, $ada->cuid.', '.$twin->cuid);

    expect(User::query()->orderBy('id')->pluck('email')->all())->toBe([EMAIL_CHANGE_OLD, 'ADA@example.com']);

    $twin->delete();
    $migration->up();
    expect(fn () => DB::table('users')->insert(['cuid' => 'cuid-of-the-other-account', 'username' => 'the-other-account', 'name' => 'Other', 'email' => 'ADA@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);
});

// ---------------------------------------------------------------------------------------------------------------------
// One budget of mails for a change of the address
// ---------------------------------------------------------------------------------------------------------------------

test('each change of the address in the profile counts: 6 a minute, also with the right password, then the form says to wait', function () {
    $ada = emailChangeMember();

    foreach (range(1, 6) as $try) {
        emailChangeRequest($ada->fresh(), 'ada.'.$try.'@example.com')->assertSessionHasNoErrors();
    }
    emailChangeRequest($ada->fresh(), 'ada.7@example.com')->assertSessionHasErrorsIn('updateProfileInformation', ['email' => 'Too many tries. Please wait a minute, then try again.']);

    expect($ada->fresh()->pending_email)->toBe('ada.6@example.com');
    Mail::assertQueuedCount(6);

    // The other fields of the profile do not count
    $this->actingAs($ada->fresh())->put('/user/profile-information', ['name' => 'Ada King', 'email' => EMAIL_CHANGE_OLD])->assertSessionHasNoErrors();
    expect($ada->fresh()->name)->toBe('Ada King');

    $this->travel(61)->seconds();
    emailChangeRequest($ada->fresh(), 'ada.7@example.com')->assertSessionHasNoErrors();
    expect($ada->fresh()->pending_email)->toBe('ada.7@example.com');
});

test('the changes of the address in the profile and "Send the link again" share one budget', function () {
    $ada = emailChangeMember();

    foreach (range(1, 6) as $try) {
        emailChangeRequest($ada->fresh(), 'ada.'.$try.'@example.com')->assertSessionHasNoErrors();
    }
    $this->actingAs($ada->fresh())->post('/user/email-change/resend')->assertStatus(429);
    Sanctum::actingAs($ada->fresh(), ['user:write']);
    $this->postJson('/api/v1/'.ApiRoutes::USER_EMAIL_CHANGE_RESEND)->assertStatus(429);
    Mail::assertQueuedCount(6);
});

test('each correction of an address that was never verified counts in the same budget', function () {
    Notification::fake();
    $grace = emailChangeNeverVerified();

    foreach (range(1, 6) as $try) {
        emailChangeRequest($grace->fresh(), 'grace.'.$try.'@example.com')->assertSessionHasNoErrors();
    }
    emailChangeRequest($grace->fresh(), 'grace.7@example.com')->assertSessionHasErrorsIn('updateProfileInformation', ['email']);

    expect($grace->fresh()->email)->toBe('grace.6@example.com');
    Notification::assertCount(6);
});

// ---------------------------------------------------------------------------------------------------------------------
// Hidden from every serialization, and the log
// ---------------------------------------------------------------------------------------------------------------------

test('the pending address is hidden from every serialization of the model: the own resource and the account of the page add it', function () {
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();

    expect($ada->fresh()->toArray())->not->toHaveKey('pending_email')->not->toHaveKey('pending_email_requested_at')
        ->and($ada->fresh()->ownPendingEmailChange()['pending_email'])->toBe(EMAIL_CHANGE_NEW);

    $this->actingAs($ada->fresh())->get('/user/profile')->assertInertia(fn ($page) => $page
        ->where('auth.user.pending_email', EMAIL_CHANGE_NEW)
        ->where('auth.user.pending_email_requested_at', Carbon::parse(EMAIL_CHANGE_NOW)->toJSON()));

    // After the period both read null, in the account of the page and in the own resource
    $this->travel(7)->days();
    $this->actingAs($ada->fresh())->get('/user/profile')->assertInertia(fn ($page) => $page
        ->where('auth.user.pending_email', null)
        ->where('auth.user.pending_email_requested_at', null));
    $this->actingAs($ada->fresh())->getJson('/api/v1/'.ApiRoutes::USER)->assertOk()
        ->assertJsonPath('data.pending_email', null)
        ->assertJsonPath('data.pending_email_requested_at', null);
});

test('the line of the log of a request is written after the commit, with the cuid only', function () {
    $ada = emailChangeMember();
    $outside = DB::transactionLevel();
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, static function (MessageLogged $event) use ($logged): void {
        $logged[] = ['message' => $event->message, 'context' => $event->context, 'transactions' => DB::transactionLevel()];
    });

    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();

    $lines = array_values(array_filter($logged->getArrayCopy(), static fn (array $line): bool => $line['message'] === 'Change of the email address asked'));
    expect($lines)->toHaveCount(1)
        ->and($lines[0]['context'])->toBe(['user' => $ada->cuid])
        ->and($lines[0]['transactions'])->toBe($outside);
});

test('the period of a pending address comes from the configuration', function () {
    Storage::fake('local');
    config(['retention.pending_email_days' => 3]);
    $ada = emailChangeMember();
    emailChangeRequest($ada, EMAIL_CHANGE_NEW)->assertSessionHasNoErrors();

    $this->travel(2)->days();
    $this->artisan('retention:prune')->assertSuccessful();
    expect($ada->fresh()->pending_email)->toBe(EMAIL_CHANGE_NEW);

    $this->travel(1)->days();
    expect($ada->fresh()->pending_email)->toBeNull();
    $this->artisan('retention:prune')->assertSuccessful();
    expect($ada->fresh()->getRawOriginal('pending_email'))->toBeNull()
        ->and(Retention::periods()['pending_email'])->toBe('3 days');
});
