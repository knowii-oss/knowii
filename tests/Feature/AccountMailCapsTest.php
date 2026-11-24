<?php

use App\Mail\ConfirmNewEmailAddress;
use App\Mail\EmailAddressHasAccount;
use App\Mail\FinishSignUp;
use App\Mail\MagicLink;
use App\Mail\SignUpAccountExists;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
 * An address gets at most 10 account mails of each kind a day: the sign-in link, the sign-up mail (both kinds), the
 * password reset mail and the mail of a change of the address (both kinds). The forms answer as before. A reset link is
 * replaced only after 5 minutes, and an account changes its address at most 5 times a day.
 */

const MAIL_CAPS_ADDRESS = 'ada@example.com';

const MAIL_CAPS_PASSWORD = 'A-new-password-of-2026!';

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-24 08:00:00'));
});

/**
 * Sends the same form 11 times in one day, each time from a new IP address and a few minutes after the one before: the
 * answers of the form, without the values that change from one request to the next
 *
 * @param  Closure(): TestResponse  $send
 * @return list<string>
 */
function mailCapsElevenTimes(Closure $send, int $minutesBetween = 6): array
{
    $answers = [];
    foreach (range(1, 11) as $i) {
        newBrowser();
        test()->withServerVariables(['REMOTE_ADDR' => "10.9.0.$i"]);
        $response = $send();
        $answers[] = $response->status().' '.$response->headers->get('Location').' '.json_encode(session('status'));
        test()->travel($minutesBetween)->minutes();
    }

    return $answers;
}

test('the sign-in link: the 11th mail of a day to one address is not sent, and the form answers as before', function () {
    Mail::fake();
    User::factory()->withUserProfile()->create(['email' => MAIL_CAPS_ADDRESS]);

    $answers = mailCapsElevenTimes(fn () => test()->post('/login/link', ['email' => MAIL_CAPS_ADDRESS]));

    expect(array_unique($answers))->toHaveCount(1);
    Mail::assertQueuedCount(10);
    expect(Mail::queued(MagicLink::class))->toHaveCount(10);

    // The next day the address gets a link again
    $this->travel(1)->days();
    $this->post('/login/link', ['email' => MAIL_CAPS_ADDRESS]);
    expect(Mail::queued(MagicLink::class))->toHaveCount(11);
});

test('the sign-up mail: the 11th mail of a day to one address is not sent, for both kinds, and the form answers as before', function (bool $account) {
    Mail::fake();
    if ($account) {
        User::factory()->withUserProfile()->create(['email' => MAIL_CAPS_ADDRESS]);
    }

    $answers = mailCapsElevenTimes(fn () => test()->post('/register', ['name' => 'Ada Lovelace', 'email' => MAIL_CAPS_ADDRESS]));

    expect(array_unique($answers))->toHaveCount(1);
    expect(Mail::queued($account ? SignUpAccountExists::class : FinishSignUp::class))->toHaveCount(10);
})->with(['an address with an account' => [true], 'an address without an account' => [false]]);

test('the sign-up mails of both kinds count together for one address', function () {
    Mail::fake();

    mailCapsElevenTimes(function () {
        // The address gets an account after the 6th mail
        if (Mail::queued(FinishSignUp::class)->count() === 6 && User::query()->where('email', MAIL_CAPS_ADDRESS)->doesntExist()) {
            User::factory()->withUserProfile()->create(['email' => MAIL_CAPS_ADDRESS]);
        }

        return test()->post('/register', ['name' => 'Ada Lovelace', 'email' => MAIL_CAPS_ADDRESS]);
    });

    expect(Mail::queued(FinishSignUp::class)->count() + Mail::queued(SignUpAccountExists::class)->count())->toBe(10);
});

test('the reset mail: the 11th mail of a day to one address is not sent, and the form answers as before', function () {
    Notification::fake();
    $user = User::factory()->withUserProfile()->create(['email' => MAIL_CAPS_ADDRESS]);

    $answers = mailCapsElevenTimes(fn () => test()->post('/forgot-password', ['email' => MAIL_CAPS_ADDRESS]));

    expect(array_unique($answers))->toHaveCount(1);
    Notification::assertSentToTimes($user, ResetPasswordLink::class, 10);
});

test('a second reset request inside 5 minutes sends no mail and keeps the first link', function () {
    Notification::fake();
    $user = User::factory()->withUserProfile()->create(['email' => MAIL_CAPS_ADDRESS]);

    $this->post('/forgot-password', ['email' => MAIL_CAPS_ADDRESS]);
    $token = null;
    Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->travel(4)->minutes();
    newBrowser();
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.1.2'])->post('/forgot-password', ['email' => MAIL_CAPS_ADDRESS]);
    Notification::assertSentToTimes($user, ResetPasswordLink::class, 1);

    // The link of the first mail still works
    newBrowser();
    $this->post('/reset-password', ['token' => $token, 'email' => MAIL_CAPS_ADDRESS, 'password' => MAIL_CAPS_PASSWORD, 'password_confirmation' => MAIL_CAPS_PASSWORD])->assertSessionHasNoErrors();
});

test('a reset request after 5 minutes sends a new link', function () {
    Notification::fake();
    $user = User::factory()->withUserProfile()->create(['email' => MAIL_CAPS_ADDRESS]);

    $this->post('/forgot-password', ['email' => MAIL_CAPS_ADDRESS]);
    $this->travel(301)->seconds();
    newBrowser();
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.1.3'])->post('/forgot-password', ['email' => MAIL_CAPS_ADDRESS]);

    Notification::assertSentToTimes($user, ResetPasswordLink::class, 2);
});

test('the mail of a change of the address: the 11th mail of a day to one free address is not sent, and the forms answer as before', function () {
    Mail::fake();
    $ada = User::factory()->withUserProfile()->create();

    $this->actingAs($ada)->put('/user/profile-information', ['name' => $ada->name, 'email' => 'new@example.org', 'current_password' => 'password'])->assertSessionHasNoErrors();
    $answers = [];
    foreach (range(1, 10) as $i) {
        $this->travel(61)->seconds();
        $response = $this->actingAs($ada->fresh())->post('/user/email-change/resend');
        $answers[] = $response->status().' '.json_encode($response->getSession()?->get('errors'));
    }

    expect(array_unique($answers))->toHaveCount(1);
    expect(Mail::queued(ConfirmNewEmailAddress::class))->toHaveCount(10);
});

test('the mails of a change of the address count both kinds together for one address', function () {
    Mail::fake();
    // Accounts that ask for the address of another account: "This address already has an account", then the address is
    // free again, and the link
    User::factory()->withUserProfile()->create(['email' => 'taken@example.org']);
    $askers = User::factory()->withUserProfile()->count(11)->create();

    foreach ($askers as $i => $asker) {
        if ($i === 5) {
            User::query()->where('email', 'taken@example.org')->update(['email' => 'moved@example.org']);
        }
        $this->actingAs($asker)->put('/user/profile-information', ['name' => $asker->name, 'email' => 'taken@example.org', 'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->travel(13)->minutes();
    }

    expect(Mail::queued(EmailAddressHasAccount::class)->count() + Mail::queued(ConfirmNewEmailAddress::class)->count())->toBe(10);
});

test('an account changes its address at most 5 times a day', function () {
    Mail::fake();
    $ada = User::factory()->withUserProfile()->create();

    foreach (range(1, 5) as $i) {
        $this->actingAs($ada->fresh())->put('/user/profile-information', ['name' => $ada->name, 'email' => "new$i@example.org", 'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->travel(61)->seconds();
    }
    $this->actingAs($ada->fresh())->put('/user/profile-information', ['name' => $ada->name, 'email' => 'new6@example.org', 'current_password' => 'password'])
        ->assertSessionHasErrorsIn('updateProfileInformation', 'email');
    expect(Mail::queued(ConfirmNewEmailAddress::class))->toHaveCount(5);

    // The next day it can again
    $this->travel(1)->days();
    $this->actingAs($ada->fresh())->put('/user/profile-information', ['name' => $ada->name, 'email' => 'new7@example.org', 'current_password' => 'password'])->assertSessionHasNoErrors();
});

test('after the 10th mail of a kind in a day, one more mail of that kind goes at most once every 3 hours', function (string $kind) {
    Mail::fake();
    Notification::fake();
    $user = User::factory()->withUserProfile()->create(['email' => MAIL_CAPS_ADDRESS]);
    $send = function (string $ip) use ($kind): void {
        newBrowser();
        test()->withServerVariables(['REMOTE_ADDR' => $ip])->post($kind === 'reset' ? '/forgot-password' : '/login/link', ['email' => MAIL_CAPS_ADDRESS])->assertSessionHasNoErrors();
    };
    $sent = fn (): int => $kind === 'reset'
        ? Notification::sent($user, ResetPasswordLink::class)->count()
        : Mail::queued(MagicLink::class)->count();

    // 10 mails, 6 minutes apart: the 10th at 08:54
    foreach (range(1, 10) as $i) {
        $send("10.9.2.$i");
        $this->travel(6)->minutes();
    }
    expect($sent())->toBe(10);

    // Inside the 3 hours after the 10th: nothing
    foreach (['09:30:00', '10:54:00', '11:53:00'] as $time) {
        $this->travelTo(Carbon::parse("2026-11-24 $time"));
        $send('192.0.2.80');
    }
    expect($sent())->toBe(10);

    // 3 hours after the 10th: one mail, then nothing for 3 more hours
    $this->travelTo(Carbon::parse('2026-11-24 11:54:01'));
    $send('192.0.2.81');
    expect($sent())->toBe(11);
    $this->travelTo(Carbon::parse('2026-11-24 12:30:00'));
    $send('192.0.2.82');
    $this->travelTo(Carbon::parse('2026-11-24 14:53:00'));
    $send('192.0.2.83');
    expect($sent())->toBe(11);
    $this->travelTo(Carbon::parse('2026-11-24 14:55:00'));
    $send('192.0.2.84');
    expect($sent())->toBe(12);
})->with(['the sign-in link' => ['link'], 'the reset mail' => ['reset']]);
