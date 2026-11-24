<?php

use App\ApiRoutes;
use App\Contracts\Users\ChangesEmailAddresses;
use App\Jobs\SendEmailChangeMail;
use App\Mail\ConfirmNewEmailAddress;
use App\Mail\EmailAddressHasAccount;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/*
 * The profile form gives the same answer for every new address (ADR 0058): a new address that another account has gets a
 * mail that says so, in place of the link, and the account keeps its address.
 */

const ECSA_PASSWORD = 'a-Long-Password-9!';

/**
 * The account that asks for the change: a verified one, or one whose address was never verified
 */
function ecsaAccount(bool $verified): User
{
    $factory = User::factory()->withUserProfile();

    return ($verified ? $factory : $factory->unverified())->create([
        'name' => 'Ada Lovelace', 'username' => 'ada-lovelace', 'email' => 'ada@example.com', 'password' => Hash::make(ECSA_PASSWORD),
    ]);
}

/**
 * The cases of a new address: the address that the form gets, after the other accounts of the case are made
 */
function ecsaCase(string $case): string
{
    return match ($case) {
        'a free address' => 'ada.new@example.com',
        'the address of another verified account' => tap('charles@example.com', fn () => User::factory()->create(['email' => 'charles@example.com'])),
        'the address of another account that was never verified' => tap('charles@example.com', fn () => User::factory()->unverified()->create(['email' => 'charles@example.com'])),
        'the address of another account in another case' => tap('Charles@Example.COM', fn () => User::factory()->create(['email' => 'charles@example.com'])),
        'the pending address of another account' => tap('ada.new@example.com', fn () => User::factory()->create(['email' => 'charles@example.com', 'pending_email' => 'ada.new@example.com', 'pending_email_requested_at' => now()])),
    };
}

/**
 * @return list<string>
 */
function ecsaCases(): array
{
    return [
        'a free address',
        'the address of another verified account',
        'the address of another account that was never verified',
        'the address of another account in another case',
        'the pending address of another account',
    ];
}

function ecsaRequest(User $user, string $address, string $kind): TestResponse
{
    $fields = ['name' => $user->name, 'email' => $address, 'current_password' => ECSA_PASSWORD];

    return $kind === 'json'
        ? test()->actingAs($user)->putJson('/user/profile-information', $fields)
        : test()->actingAs($user)->from('/user/profile')->put('/user/profile-information', $fields, ['X-Inertia' => 'true']);
}

/**
 * The one row of `jobs` of the change: its raw text and its command, read as the worker reads it
 *
 * @return array{raw: string, command: mixed}
 */
function ecsaQueuedJob(): array
{
    $row = DB::table('jobs')->where('payload', 'like', '%SendEmailChangeMail%')->sole();
    $command = (string) json_decode((string) $row->payload, true)['data']['command'];

    return [
        'raw' => (string) json_encode($row, JSON_UNESCAPED_SLASHES).(string) base64_decode((string) $row->payload, true),
        'command' => unserialize((string) app('encrypter')->decrypt($command)),
    ];
}

function ecsaRunWorker(): void
{
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'default', '--sleep' => 0, '--tries' => 1]);
}

// ---------------------------------------------------------------------------------------------------------------------
// The answer
// ---------------------------------------------------------------------------------------------------------------------

test('the profile form gives the same answer for a new address that another account has and one that no account has', function (string $case, bool $verified, string $kind) {
    config(['queue.default' => 'database']);
    $ada = ecsaAccount($verified);
    $address = ecsaCase($case);

    $response = ecsaRequest($ada, $address, $kind);

    // The same answer in every case: the one of a saved profile, with no error
    $kind === 'json'
        ? $response->assertOk()->assertContent('""')
        : $response->assertStatus(303)->assertRedirect('/user/profile')->assertSessionHasNoErrors()->assertSessionHas('status', 'profile-information-updated');
    $row = $ada->fresh();
    expect($row->email)->toBe('ada@example.com')
        ->and($row->pending_email)->toBe(mb_strtolower($address))
        ->and($row->hasVerifiedEmail())->toBe($verified);

    $queued = ecsaQueuedJob();
    expect(DB::table('jobs')->count())->toBe(1)
        ->and($queued['command'])->toBeInstanceOf(SendEmailChangeMail::class)
        ->and($queued['raw'])->not->toContain(mb_strtolower($address))
        ->and($queued['raw'])->not->toContain('email-change');

    // The next profile page: the address of the account, and the new address as the pending address
    $this->actingAs($row)->get('/user/profile')->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.user.email', 'ada@example.com')->where('auth.user.pending_email', mb_strtolower($address)));
})->with(ecsaCases())->with(['a verified account' => true, 'an account that was never verified' => false])->with(['inertia', 'json']);

test('the request reads no other account by its address, and runs the same statements for a free and a taken address', function (bool $verified) {
    config(['queue.default' => 'database']);
    $statements = [];
    foreach (['a free address', 'the address of another verified account'] as $case) {
        User::query()->get()->each->delete();
        app('auth')->forgetGuards();
        $ada = ecsaAccount($verified);
        // The same new address, with and without another account
        $address = 'charles@example.com';
        if ($case !== 'a free address') {
            User::factory()->create(['email' => $address]);
        }
        $sql = new ArrayObject;
        DB::listen(function (QueryExecuted $query) use ($sql) {
            $sql[] = $query->sql;
        });

        ecsaRequest($ada, $address, 'inertia')->assertSessionHasNoErrors();
        Event::forget(QueryExecuted::class);

        // The ids of the accounts of each case are not part of the comparison
        $statements[$case] = array_map(fn (string $query) => preg_replace('/\(\d+(, \d+)*\)/', '(?)', $query), $sql->getArrayCopy());
        expect(collect($statements[$case])->filter(fn (string $query) => str_contains($query, 'lower(email)'))->all())->toBe([], $case);
    }

    expect($statements['a free address'])->toBe($statements['the address of another verified account']);
})->with(['a verified account' => true, 'an account that was never verified' => false]);

// ---------------------------------------------------------------------------------------------------------------------
// The job
// ---------------------------------------------------------------------------------------------------------------------

test('the job sends the link to a free address, and the mail without a link to an address that another account has; the pending address stays', function (string $case) {
    Mail::fake();
    $ada = ecsaAccount(true);
    $address = mb_strtolower(ecsaCase($case));

    ecsaRequest($ada, $address, 'inertia')->assertSessionHasNoErrors();

    if ($case === 'a free address' || $case === 'the pending address of another account') {
        Mail::assertQueued(ConfirmNewEmailAddress::class, fn (ConfirmNewEmailAddress $mail) => $mail->hasTo($address));
        Mail::assertNotQueued(EmailAddressHasAccount::class);
    } else {
        Mail::assertQueued(EmailAddressHasAccount::class, fn (EmailAddressHasAccount $mail) => $mail->hasTo($address));
        Mail::assertNotQueued(ConfirmNewEmailAddress::class);
    }
    Mail::assertQueuedCount(1);
    expect($ada->fresh()->pending_email)->toBe($address)
        ->and($ada->fresh()->email)->toBe('ada@example.com');
})->with(ecsaCases());

test('the mail without a link has its words and no link to the change', function () {
    $mail = new EmailAddressHasAccount;

    expect($mail->render())->toContain('This address already has an account on Knowii. Someone asked to use it for another account, so we did not change anything.')
        ->toContain('If it was you, sign in to the account of this address, or choose another address.')
        ->toContain('If it was not you, ignore this email: nothing changed in your account.')
        ->not->toContain('/user/email-change/')
        ->and($mail->build()->subject)->toBe('This address already has an account');
});

test('a job whose address is not the pending address any more sends nothing: a newer change, a cancel', function () {
    config(['queue.default' => 'database']);
    Mail::fake();
    $ada = ecsaAccount(true);

    ecsaRequest($ada, 'first@example.com', 'inertia')->assertSessionHasNoErrors();
    $first = ecsaQueuedJob()['command'];
    DB::table('jobs')->delete();
    ecsaRequest($ada->fresh(), 'second@example.com', 'inertia')->assertSessionHasNoErrors();
    DB::table('jobs')->delete();

    $first->handle(app(ChangesEmailAddresses::class));
    Mail::assertNothingQueued();

    $this->actingAs($ada->fresh())->delete('/user/email-change')->assertRedirect();
    ecsaRequest($ada->fresh(), 'third@example.com', 'inertia')->assertSessionHasNoErrors();
    $third = ecsaQueuedJob()['command'];
    // A new request: the guard of the API keeps the account of the request of before
    app('auth')->forgetGuards();
    $this->actingAs($ada->fresh())->delete('/user/email-change')->assertRedirect();
    expect($ada->fresh()->pending_email)->toBeNull();
    $third->handle(app(ChangesEmailAddresses::class));
    Mail::assertNothingQueued();
});

test('the rows of the queue hold no address and no link outside the ciphertext', function (string $case) {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    $ada = ecsaAccount(true);
    $address = mb_strtolower(ecsaCase($case));

    ecsaRequest($ada, $address, 'inertia')->assertSessionHasNoErrors();
    ecsaRunWorker();

    $raw = DB::table('jobs')->get()->map(fn ($row) => json_encode($row, JSON_UNESCAPED_SLASHES).base64_decode((string) $row->payload, true))->implode("\n");
    expect(DB::table('jobs')->count())->toBe(1)
        ->and($raw)->not->toContain($address)
        ->and($raw)->not->toContain('email-change');
})->with(['a free address', 'the address of another verified account']);

test('a job of the change that meets an error ends as done, with a log line that holds no address', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = [$event->message, $event->context];
    });
    $ada = ecsaAccount(true);

    ecsaRequest($ada, 'ada.new@example.com', 'inertia')->assertSessionHasNoErrors();
    DB::listen(function (QueryExecuted $query) {
        if (str_contains($query->sql, 'lower(email) = ?')) {
            throw new RuntimeException('The read of ada.new@example.com failed');
        }
    });
    ecsaRunWorker();

    $lines = collect($logged)->filter(fn (array $line): bool => $line[0] === 'An email change job ended with an error')->values();
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and($lines)->toHaveCount(1)
        ->and($lines[0][1]['error'])->toBe(RuntimeException::class)
        ->and((string) json_encode($logged->getArrayCopy()))->not->toContain('ada.new@example.com');
});

// ---------------------------------------------------------------------------------------------------------------------
// "Send the link again"
// ---------------------------------------------------------------------------------------------------------------------

test('"Send the link again", on the web and in the API, gives the same answer for a free and a taken address, and the right mail', function (string $case, string $how) {
    Mail::fake();
    $ada = ecsaAccount(true);
    $address = mb_strtolower(ecsaCase($case));
    ecsaRequest($ada, $address, 'inertia')->assertSessionHasNoErrors();
    // At most one mail without a link a minute per recipient
    $this->travel(61)->seconds();

    if ($how === 'web') {
        $this->actingAs($ada->fresh())->from('/user/profile')->post('/user/email-change/resend')
            ->assertRedirect('/user/profile')
            ->assertSessionHas('flash', ['banner' => 'We sent the link again.', 'bannerStyle' => 'success']);
    } else {
        Sanctum::actingAs($ada->fresh(), ['user:write']);
        $this->postJson(ApiRoutes::path(ApiRoutes::USER_EMAIL_CHANGE_RESEND))->assertOk();
    }

    $class = $case === 'a free address' ? ConfirmNewEmailAddress::class : EmailAddressHasAccount::class;
    Mail::assertQueued($class, 2);
    Mail::assertQueuedCount(2);
})->with(['a free address', 'the address of another verified account'])->with(['web', 'api']);

// ---------------------------------------------------------------------------------------------------------------------
// The caps of the mail without a link, and a link mail that fails
// ---------------------------------------------------------------------------------------------------------------------

test('the mail without a link goes at most once a minute and 5 times an hour to one recipient, whatever the account that asks; the answer does not change', function () {
    Mail::fake();
    User::factory()->create(['email' => 'charles@example.com']);
    $ada = ecsaAccount(true);
    $grace = User::factory()->withUserProfile()->create(['email' => 'grace@example.com', 'password' => Hash::make(ECSA_PASSWORD)]);

    ecsaRequest($ada, 'charles@example.com', 'inertia')->assertSessionHasNoErrors();
    app('auth')->forgetGuards();
    ecsaRequest($grace, 'Charles@Example.com', 'inertia')->assertSessionHasNoErrors()->assertSessionHas('status', 'profile-information-updated');
    Mail::assertQueued(EmailAddressHasAccount::class, 1);

    // One a minute: 5 in the hour, then nothing more until the hour is over
    foreach (range(1, 6) as $minute) {
        $this->travel(61)->seconds();
        app(ChangesEmailAddresses::class)->resend($ada->fresh());
    }
    Mail::assertQueued(EmailAddressHasAccount::class, 5);

    $this->travel(1)->hours();
    app(ChangesEmailAddresses::class)->resend($ada->fresh());
    Mail::assertQueued(EmailAddressHasAccount::class, 6);
    // The link to a free address has no such cap
    Mail::assertNotQueued(ConfirmNewEmailAddress::class);
});

test('a link mail of a change that fails keeps no recipient address: neither failed_jobs nor the log gets the text of the provider', function () {
    config(['queue.default' => 'database']);
    Mail::extend('rejecting', static fn () => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('Expected response code "250" but got code "550", with message "550 5.1.1 <ada.new@example.com>: Recipient address rejected".');
        }

        public function __toString(): string
        {
            return 'rejecting';
        }
    });
    config(['mail.mailers.rejecting' => ['transport' => 'rejecting'], 'mail.default' => 'rejecting']);
    Mail::purge('rejecting');
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $exception = $event->context['exception'] ?? null;
        $logged[] = $event->message.' '.($exception instanceof Throwable ? (string) $exception : (string) json_encode($event->context));
    });
    $ada = ecsaAccount(true);

    ecsaRequest($ada, 'ada.new@example.com', 'inertia')->assertSessionHasNoErrors();
    DB::table('jobs')->where('payload', 'not like', '%SendEmailChangeMail%')->delete();
    ecsaRunWorker();
    ecsaRunWorker();

    $failed = DB::table('failed_jobs')->sole();
    $text = (string) json_encode($failed, JSON_UNESCAPED_SLASHES).implode("\n", $logged->getArrayCopy());
    expect((string) $failed->exception)->toContain('The mail could not be sent (Symfony\\Component\\Mailer\\Exception\\TransportException)')
        ->and($text)->not->toContain('ada.new@example.com')
        ->and($text)->not->toContain('Recipient address rejected');
});
