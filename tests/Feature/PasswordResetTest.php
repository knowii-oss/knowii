<?php

use App\Jobs\SendPasswordResetLink;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Laravel\Fortify\Features;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/*
 * The password reset form gives the same answer for every address, and the reset mail goes through the queue, encrypted,
 * as the other mails that hold a link (documentation/architecture/backend.md, ADR 0064).
 */

const PASSWORD_RESET_EMAIL = 'ada@example.com';

const PASSWORD_RESET_NEW_PASSWORD = 'A-new-password-of-2026!';

const PASSWORD_RESET_ANSWER = 'If an account has this address, we sent it a password reset link. The link works for 60 minutes.';

/**
 * The account of the reset, with a verified address and no second factor
 */
function passwordResetAccount(array $attributes = []): User
{
    return User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => PASSWORD_RESET_EMAIL, ...$attributes]);
}

/**
 * The mails that the array transport holds, as text
 *
 * @return list<string>
 */
function passwordResetSentMails(): array
{
    $transport = Mail::mailer('array')->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);

    return $transport instanceof ArrayTransport
        ? collect($transport->messages())
            ->map(static fn ($message): string => (string) $message->getOriginalMessage()->getTextBody())
            ->values()
            ->all()
        : [];
}

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
})->skip(function () {
    return ! Features::enabled(Features::resetPasswords());
}, 'Password updates are not enabled.');

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, ResetPasswordLink::class);
})->skip(function () {
    return ! Features::enabled(Features::resetPasswords());
}, 'Password updates are not enabled.');

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, ResetPasswordLink::class, function (object $notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
})->skip(function () {
    return ! Features::enabled(Features::resetPasswords());
}, 'Password updates are not enabled.');

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, ResetPasswordLink::class, function (object $notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors();

        return true;
    });
})->skip(function () {
    return ! Features::enabled(Features::resetPasswords());
}, 'Password updates are not enabled.');

// ---------------------------------------------------------------------------------------------------------------------
// The same answer for every address
// ---------------------------------------------------------------------------------------------------------------------

test('the reset form gives the same answer for every address, and a mail goes only to the address of an account', function (string $case, string $kind) {
    Notification::fake();
    $user = match ($case) {
        'an address with an account' => passwordResetAccount(),
        'an address without an account' => null,
        'the pending address of a member' => passwordResetAccount(['pending_email' => 'ada.new@example.org', 'pending_email_requested_at' => now()]),
        'a request that the broker throttles' => passwordResetAccount(),
    };
    if ($case === 'a request that the broker throttles') {
        Password::broker()->createToken($user);
    }
    $address = $case === 'the pending address of a member' ? 'ada.new@example.org' : ($user !== null ? PASSWORD_RESET_EMAIL : 'nobody@example.com');

    if ($kind === 'json') {
        $this->postJson('/forgot-password', ['email' => $address])
            ->assertStatus(200)
            ->assertExactJson(['message' => PASSWORD_RESET_ANSWER]);
    } else {
        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $address], $kind === 'inertia' ? ['X-Inertia' => 'true'] : [])
            ->assertStatus(302)
            ->assertRedirect('/forgot-password')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', PASSWORD_RESET_ANSWER)
            ->assertSessionMissing('_old_input');
    }

    if ($case === 'an address with an account') {
        Notification::assertSentTo($user, ResetPasswordLink::class);
        Notification::assertSentTimes(ResetPasswordLink::class, 1);
    } else {
        Notification::assertNothingSent();
    }
})->with([
    'an address with an account',
    'an address without an account',
    'the pending address of a member',
    'a request that the broker throttles',
])->with(['inertia', 'form', 'json']);

test('a malformed address still gets the error of the format', function () {
    Notification::fake();

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'not-an-address'])->assertSessionHasErrors('email');
    $response = $this->postJson('/forgot-password', ['email' => 'not-an-address'])
        ->assertJsonPath('errors.email.0', 'The email field must be a valid email address.');
    expect($response->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------------------------------------------------
// The work of the form runs in a queued job, and the reset mail goes through the queue, encrypted
// ---------------------------------------------------------------------------------------------------------------------

/**
 * The one row of `jobs` whose payload names this class: its raw text, as the database stores it, and its command, read
 * as the worker reads it
 *
 * @return array{raw: string, command: mixed, queue: string}
 */
function passwordResetQueuedJob(string $class): array
{
    $row = DB::table('jobs')->where('payload', 'like', '%'.str_replace('\\', '%', $class).'%')->sole();
    $command = (string) json_decode((string) $row->payload, true)['data']['command'];
    expect(str_starts_with($command, 'O:'))->toBeFalse();

    return [
        'raw' => (string) json_encode($row, JSON_UNESCAPED_SLASHES).(string) base64_decode((string) $row->payload, true),
        'command' => unserialize((string) app('encrypter')->decrypt($command)),
        'queue' => (string) $row->queue,
    ];
}

/**
 * Run one job of the queue `default` with a real worker
 */
function passwordResetRunWorker(): void
{
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'default', '--sleep' => 0, '--tries' => 1]);
}

test('the request only queues the job of the form: no token and no mail inside the request', function (bool $account) {
    Queue::fake();
    if ($account) {
        passwordResetAccount();
    }
    $address = $account ? PASSWORD_RESET_EMAIL : 'nobody@example.com';

    $this->from('/forgot-password')->post('/forgot-password', ['email' => strtoupper($address)])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', PASSWORD_RESET_ANSWER);

    Queue::assertPushed(SendPasswordResetLink::class, 1);
    Queue::assertPushed(SendPasswordResetLink::class, fn (SendPasswordResetLink $job): bool => $job->email === $address);
    Queue::assertNotPushed(SendQueuedNotifications::class);
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
})->with(['an address with an account' => true, 'an address without an account' => false]);

test('the job of the form waits in the queue default, encrypted: its row holds no address', function (bool $account) {
    config(['queue.default' => 'database', 'mail.default' => 'array', 'mail.queue' => 'mail']);
    if ($account) {
        passwordResetAccount();
    }
    $address = $account ? PASSWORD_RESET_EMAIL : 'nobody@example.com';

    $this->post('/forgot-password', ['email' => $address])->assertSessionHasNoErrors();

    $queued = passwordResetQueuedJob(SendPasswordResetLink::class);
    expect($queued['queue'])->toBe('default')
        ->and($queued['command'])->toBeInstanceOf(SendPasswordResetLink::class)
        ->and($queued['command'] instanceof SendPasswordResetLink ? $queued['command']->email : null)->toBe($address)
        ->and($queued['raw'])->not->toContain($address)
        ->and($queued['raw'])->not->toContain(rawurlencode($address))
        ->and(passwordResetSentMails())->toBe([])
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);
})->with(['an address with an account' => true, 'an address without an account' => false]);

test('the job of the form queues the reset mail on the queue default, and its row holds neither the token nor the address', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array', 'mail.queue' => 'mail']);
    passwordResetAccount();

    $this->post('/forgot-password', ['email' => PASSWORD_RESET_EMAIL])->assertSessionHasNoErrors();
    DB::table('jobs')->where('payload', 'not like', '%SendPasswordResetLink%')->delete();
    passwordResetRunWorker();

    $queued = passwordResetQueuedJob(ResetPasswordLink::class);
    $job = $queued['command'];
    expect($queued['queue'])->toBe('default')->and($job)->toBeInstanceOf(SendQueuedNotifications::class);
    $notification = $job instanceof SendQueuedNotifications ? $job->notification : null;
    expect($notification)->toBeInstanceOf(ResetPassword::class);
    $token = $notification instanceof ResetPassword ? $notification->token : '';
    expect($token)->not->toBe('')
        ->and($queued['raw'])->not->toContain($token)
        ->and($queued['raw'])->not->toContain(PASSWORD_RESET_EMAIL)
        ->and($queued['raw'])->not->toContain(rawurlencode(PASSWORD_RESET_EMAIL))
        ->and($queued['raw'])->not->toContain('reset-password')
        ->and(passwordResetSentMails())->toBe([]);
});

test('two jobs for the same address at the same moment end with one token, one mail and no error, and no log line holds the address', function () {
    Notification::fake();
    $user = passwordResetAccount();
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    // The other job commits its token row on its own connection after this job read the throttle and deleted the row,
    // before its insert
    config(['database.connections.password_reset_other_job' => config('database.connections.'.config('database.default'))]);
    $other = DB::connection('password_reset_other_job');
    $done = false;
    DB::listen(function (QueryExecuted $query) use (&$done, $other) {
        if (! $done && $query->connectionName !== 'password_reset_other_job' && str_starts_with($query->sql, 'delete from "password_reset_tokens"')) {
            $done = true;
            $other->table('password_reset_tokens')->insert(['email' => PASSWORD_RESET_EMAIL, 'token' => Hash::make('the-token-of-the-other-job'), 'created_at' => now()]);
        }
    });

    try {
        (new SendPasswordResetLink(PASSWORD_RESET_EMAIL))->handle();
        $tokens = DB::table('password_reset_tokens')->where('email', PASSWORD_RESET_EMAIL)->pluck('token')->all();
    } finally {
        // The row of the other connection is not part of the transaction of the test
        $other->table('password_reset_tokens')->where('email', PASSWORD_RESET_EMAIL)->delete();
        $other->disconnect();
    }

    expect($done)->toBeTrue()
        ->and($tokens)->toHaveCount(1)
        ->and(Hash::check('the-token-of-the-other-job', (string) $tokens[0]))->toBeTrue()
        ->and(collect($logged)->filter(fn (string $line): bool => str_contains($line, 'password reset'))->values()->all())->toHaveCount(1)
        ->and(collect($logged)->contains(fn (string $line): bool => str_starts_with($line, 'A password reset link was asked for twice at the same moment')))->toBeTrue()
        ->and(collect($logged)->contains(fn (string $line): bool => str_contains(strtolower($line), PASSWORD_RESET_EMAIL)))->toBeFalse();
    // The mail of the other job is the one that goes: this one sends none
    Notification::assertNothingSent();

    expect($user->fresh())->not->toBeNull();
});

test('a job that meets an error ends as done, with a log line that holds no address, and no failed job', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    passwordResetAccount();
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = [$event->message, $event->context];
    });

    $this->post('/forgot-password', ['email' => PASSWORD_RESET_EMAIL])->assertSessionHasNoErrors();
    DB::table('jobs')->where('payload', 'not like', '%SendPasswordResetLink%')->delete();
    DB::listen(function (QueryExecuted $query) {
        if (str_starts_with($query->sql, 'insert into "password_reset_tokens"')) {
            throw new RuntimeException('The insert of '.PASSWORD_RESET_EMAIL.' failed');
        }
    });
    passwordResetRunWorker();

    $lines = collect($logged)->filter(fn (array $line): bool => $line[0] === 'A password reset job ended with an error')->values();
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0)
        ->and($lines)->toHaveCount(1)
        ->and(array_keys($lines[0][1]))->toBe(['job', 'error'])
        ->and($lines[0][1]['job'])->not->toBeNull()
        ->and($lines[0][1]['error'])->toBe(RuntimeException::class)
        ->and((string) json_encode($logged->getArrayCopy()))->not->toContain(PASSWORD_RESET_EMAIL);
});

test('the job does not wait: the steps of the broker run without its timebox', function (bool $account) {
    Notification::fake();
    Sleep::fake();
    if ($account) {
        passwordResetAccount();
    }

    $started = hrtime(true);
    (new SendPasswordResetLink($account ? PASSWORD_RESET_EMAIL : 'nobody@example.com'))->handle();
    $milliseconds = (hrtime(true) - $started) / 1_000_000;

    Sleep::assertNeverSlept();
    expect($milliseconds)->toBeLessThan(150)
        ->and(DB::table('password_reset_tokens')->count())->toBe($account ? 1 : 0);
    $account ? Notification::assertSentTimes(ResetPasswordLink::class, 1) : Notification::assertNothingSent();
})->with(['an address with an account' => true, 'an address without an account' => false]);

test('a reset mail that fails keeps no recipient address: neither failed_jobs nor the log gets the text of the provider', function () {
    config(['queue.default' => 'database']);
    Mail::extend('rejecting', static fn () => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('Expected response code "250" but got code "550", with message "550 5.1.1 <'.PASSWORD_RESET_EMAIL.'>: Recipient address rejected".');
        }

        public function __toString(): string
        {
            return 'rejecting';
        }
    });
    config(['mail.mailers.rejecting' => ['transport' => 'rejecting'], 'mail.default' => 'rejecting']);
    Mail::purge('rejecting');
    passwordResetAccount();
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $exception = $event->context['exception'] ?? null;
        $logged[] = $event->message.' '.($exception instanceof Throwable ? (string) $exception : (string) json_encode($event->context));
    });

    $this->post('/forgot-password', ['email' => PASSWORD_RESET_EMAIL])->assertSessionHasNoErrors();
    DB::table('jobs')->where('payload', 'not like', '%SendPasswordResetLink%')->delete();
    passwordResetRunWorker();
    passwordResetRunWorker();

    $failed = DB::table('failed_jobs')->sole();
    $text = (string) json_encode($failed, JSON_UNESCAPED_SLASHES).implode("\n", $logged->getArrayCopy());
    expect((string) $failed->exception)->toContain('The mail could not be sent ('.TransportException::class.')')
        ->and($text)->not->toContain(PASSWORD_RESET_EMAIL)
        ->and($text)->not->toContain('Recipient address rejected')
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('a worker sends the queued reset mail, its link opens the reset page, and the new password signs in', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    $user = passwordResetAccount();

    $this->post('/forgot-password', ['email' => PASSWORD_RESET_EMAIL])->assertSessionHasNoErrors();
    expect(passwordResetSentMails())->toBe([]);

    // Only the work of the form runs: the other jobs of the sign-up of the account are not part of this test. The first
    // job runs the broker and queues the mail, the second one sends it
    DB::table('jobs')->where('payload', 'not like', '%SendPasswordResetLink%')->delete();
    passwordResetRunWorker();
    expect(passwordResetSentMails())->toBe([]);
    passwordResetRunWorker();
    expect(DB::table('jobs')->count())->toBe(0)->and(DB::table('failed_jobs')->count())->toBe(0);

    $mails = passwordResetSentMails();
    expect($mails)->toHaveCount(1);
    expect(preg_match('#/reset-password/([A-Za-z0-9]+)\?email=([^\s)\]]+)#', $mails[0], $matches))->toBe(1);
    $token = $matches[1];
    expect(urldecode($matches[2]))->toBe(PASSWORD_RESET_EMAIL);

    $this->get('/reset-password/'.$token.'?email='.rawurlencode(PASSWORD_RESET_EMAIL))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ResetPassword')->where('token', $token));

    $this->post('/reset-password', ['token' => $token, 'email' => PASSWORD_RESET_EMAIL, 'password' => PASSWORD_RESET_NEW_PASSWORD, 'password_confirmation' => PASSWORD_RESET_NEW_PASSWORD])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));
    expect(Hash::check(PASSWORD_RESET_NEW_PASSWORD, (string) $user->fresh()?->password))->toBeTrue();

    $this->post('/login', ['email' => PASSWORD_RESET_EMAIL, 'password' => PASSWORD_RESET_NEW_PASSWORD])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

// ---------------------------------------------------------------------------------------------------------------------
// The limiter
// ---------------------------------------------------------------------------------------------------------------------

test('the reset form has the limiter of the sign-in link form: 5 a minute per address and IP, then the same 429', function () {
    Notification::fake();
    passwordResetAccount();
    $answers = [];
    foreach (['/forgot-password', '/login/link'] as $path) {
        foreach ([PASSWORD_RESET_EMAIL, 'nobody@example.com'] as $address) {
            for ($i = 0; $i < 5; $i++) {
                expect($this->postJson($path, ['email' => $address])->status())->toBeLessThan(400);
            }
            $response = $this->postJson($path, ['email' => $address])->assertStatus(429);
            $answers[] = [$response->status(), $response->json()];
        }
    }

    expect(array_unique(array_map('json_encode', $answers)))->toHaveCount(1);
    // Another address from the same IP still gets the answer of the form
    $this->postJson('/forgot-password', ['email' => 'grace@example.com'])->assertOk()->assertExactJson(['message' => PASSWORD_RESET_ANSWER]);
});

test('the reset form takes 20 requests an hour from one IP, for any addresses: the 21st gets the same 429', function () {
    Notification::fake();
    for ($i = 1; $i <= 20; $i++) {
        $this->postJson('/forgot-password', ['email' => "person{$i}@example.com"])->assertOk()->assertExactJson(['message' => PASSWORD_RESET_ANSWER]);
    }

    $refused = $this->postJson('/forgot-password', ['email' => 'person21@example.com'])->assertStatus(429);
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/login/link', ['email' => 'one@example.com']);
    }
    $linkRefused = $this->postJson('/login/link', ['email' => 'one@example.com'])->assertStatus(429);
    expect($refused->json())->toBe($linkRefused->json());

    // Another IP address is not counted, and after the hour the first one gets the answer of the form again
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->postJson('/forgot-password', ['email' => 'person22@example.com'])->assertOk();
    $this->travel(61)->minutes();
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->postJson('/forgot-password', ['email' => 'person23@example.com'])->assertOk();
});
