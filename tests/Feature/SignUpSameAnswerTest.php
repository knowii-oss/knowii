<?php

use App\Actions\Auth\PendingSignUps;
use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Inertia\SignUpLinkController;
use App\Http\Middleware\EndOutdatedSessions;
use App\Http\Responses\SignUpRequestResponse;
use App\Jobs\SendSignUpMail;
use App\Mail\FinishSignUp;
use App\Mail\SignUpAccountExists;
use App\Models\User;
use App\Support\SecretAddresses;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Http\Controllers\RegisteredUserController as FortifyRegisteredUserController;
use Symfony\Component\HttpFoundation\Response;

/*
 * The sign-up form gives the same answer for every address, and the account is made when the person opens the link of the
 * mail, with a verified address (ADR 0065).
 */

const SIGN_UP_PASSWORD = 'A-strong-password-2026!';

/**
 * The cases of an address on the sign-up form: the address that the form gets, after the accounts of the case are made
 */
function signUpCase(string $case): string
{
    return match ($case) {
        'a new address' => 'grace@example.com',
        'the address of a verified account' => tap('ada@example.com', fn () => User::factory()->withUserProfile()->create(['email' => 'ada@example.com'])),
        'the address of an account that was never verified' => tap('ada@example.com', fn () => User::factory()->unverified()->withUserProfile()->create(['email' => 'ada@example.com'])),
        'the address of an account in another case' => tap('ADA@Example.COM', fn () => User::factory()->withUserProfile()->create(['email' => 'ada@example.com'])),
        'the pending address of another account' => tap('grace@example.com', fn () => User::factory()->withUserProfile()->create(['email' => 'ada@example.com', 'pending_email' => 'grace@example.com', 'pending_email_requested_at' => now()])),
        'the address of a deleted account' => tap('ada@example.com', fn () => User::factory()->withUserProfile()->create(['email' => 'ada@example.com'])->delete()),
    };
}

/**
 * @return list<string>
 */
function signUpCases(): array
{
    return [
        'a new address',
        'the address of a verified account',
        'the address of an account that was never verified',
        'the address of an account in another case',
        'the pending address of another account',
        'the address of a deleted account',
    ];
}

function signUpPost(string $address, string $kind): TestResponse
{
    $fields = ['name' => 'Grace Hopper', 'email' => $address];

    return match ($kind) {
        'json' => test()->postJson('/register', $fields),
        'inertia' => test()->post('/register', $fields, ['X-Inertia' => 'true']),
        'form' => test()->post('/register', $fields),
    };
}

/**
 * The answer without what differs from one request to the next: the date, the nonce of the CSP and the values of the
 * cookies
 *
 * @return array{status: int, headers: array<string, mixed>, body: string, flash: mixed}
 */
function signUpAnswer(TestResponse $response): array
{
    $headers = collect($response->headers->all())
        ->except(['date'])
        ->map(fn (array $values, string $name) => array_map(
            fn ($value) => $name === 'set-cookie'
                ? preg_replace(['/=[^;]*;/', '/expires=[^;]*;/i', '/max-age=\d+/i'], ['=;', 'expires=;', 'max-age='], (string) $value)
                : preg_replace("/'nonce-[^']+'/", "'nonce'", (string) $value),
            $values,
        ))
        ->sortKeys()
        ->all();

    return [
        'status' => $response->getStatusCode(),
        'headers' => $headers,
        'body' => preg_replace('/nonce="[^"]+"/', 'nonce=""', (string) $response->getContent()),
        'flash' => session('status'),
    ];
}

/**
 * The one row of `jobs` whose payload names this class: its raw text and its command, read as the worker reads it
 *
 * @return array{raw: string, command: mixed, queue: string}
 */
function signUpQueuedJob(string $class): array
{
    $row = DB::table('jobs')->where('payload', 'like', '%'.str_replace('\\', '%', $class).'%')->sole();
    $command = (string) json_decode((string) $row->payload, true)['data']['command'];

    return [
        'raw' => (string) json_encode($row, JSON_UNESCAPED_SLASHES).(string) base64_decode((string) $row->payload, true),
        'command' => unserialize((string) app('encrypter')->decrypt($command)),
        'queue' => (string) $row->queue,
    ];
}

function signUpRunWorker(): void
{
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'default', '--sleep' => 0, '--tries' => 1]);
}

/**
 * The page of the link of a pending sign-up, as the person sends it
 *
 * @param  array<string, mixed>  $extra
 */
function signUpFinish(string $token, array $extra = []): TestResponse
{
    return test()->post('/register/link/'.$token, ['name' => 'Grace Hopper', 'password' => SIGN_UP_PASSWORD, 'password_confirmation' => SIGN_UP_PASSWORD, 'terms' => true, ...$extra]);
}

// ---------------------------------------------------------------------------------------------------------------------
// The answer of the form
// ---------------------------------------------------------------------------------------------------------------------

test('the sign-up form gives the same answer for every address, signs nobody in, makes no account and queues one encrypted job', function (string $kind) {
    config(['queue.default' => 'database']);
    $answers = [];
    foreach (signUpCases() as $index => $case) {
        // A new IP address for each case: the counts of the limiter are the same
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.($index + 10)]);
        DB::table('jobs')->delete();
        User::query()->withoutGlobalScopes()->get()->each->forceDelete();
        app('auth')->forgetGuards();
        $this->flushSession();
        $address = signUpCase($case);
        $accounts = User::query()->count();

        $response = signUpPost($address, $kind);

        $kind === 'json'
            ? $response->assertOk()->assertExactJson(['message' => SignUpRequestResponse::MESSAGE])
            : $response->assertRedirect(route('register'))->assertSessionHasNoErrors()->assertSessionHas('status', SignUpRequestResponse::MESSAGE);
        $this->assertGuest();
        expect(User::query()->count())->toBe($accounts, $case);

        $queued = signUpQueuedJob(SendSignUpMail::class);
        expect(DB::table('jobs')->count())->toBe(1)
            ->and($queued['queue'])->toBe('default')
            ->and($queued['command'])->toBeInstanceOf(SendSignUpMail::class)
            ->and($queued['raw'])->not->toContain(mb_strtolower($address))
            ->and($queued['raw'])->not->toContain(rawurlencode(mb_strtolower($address)))
            ->and($queued['raw'])->not->toContain('Grace Hopper');

        $answers[$case] = signUpAnswer($response);
    }

    expect(collect($answers)->map(fn (array $answer) => json_encode($answer))->unique())->toHaveCount(1);
})->with(['form', 'inertia', 'json']);

test('the request reads no account, and runs the same statements for an address with and without an account', function () {
    config(['queue.default' => 'database']);
    $statements = [];
    foreach (['a new address', 'the address of a verified account'] as $case) {
        User::query()->get()->each->forceDelete();
        $this->flushSession();
        $address = signUpCase($case);
        $sql = new ArrayObject;
        DB::listen(function (QueryExecuted $query) use ($sql) {
            $sql[] = $query->sql;
        });

        signUpPost($address, 'form')->assertSessionHasNoErrors();

        $statements[$case] = $sql->getArrayCopy();
        expect(collect($statements[$case])->filter(fn (string $query) => str_contains($query, '"users"'))->all())->toBe([], $case);
        Event::forget(QueryExecuted::class);
    }

    expect($statements['a new address'])->toBe($statements['the address of a verified account'])
        ->and($statements['a new address'])->toContain(collect($statements['a new address'])->first(fn (string $query) => str_starts_with($query, 'insert into "jobs"')));
});

test('a field of the form that fails gets its error, which tells nothing about an account, and nothing is queued', function (array $fields, string $field) {
    Queue::fake();
    User::factory()->create(['email' => 'ada@example.com']);

    $this->post('/register', ['name' => 'Grace Hopper', 'email' => 'ada@example.com', ...$fields])->assertSessionHasErrors($field);

    Queue::assertNothingPushed();
})->with([
    'no name' => [['name' => ''], 'name'],
    'a malformed address' => [['email' => 'not-an-address'], 'email'],
    'a long address' => [['email' => str_repeat('a', 250).'@example.com'], 'email'],
]);

test('the sign-up page shows the answer in the place of the form', function () {
    Queue::fake();

    $this->post('/register', ['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    $this->get('/register')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Register')->where('status', SignUpRequestResponse::MESSAGE));
    $this->get('/register')->assertInertia(fn ($page) => $page->where('status', null));
});

// ---------------------------------------------------------------------------------------------------------------------
// The job
// ---------------------------------------------------------------------------------------------------------------------

test('the job sends the mail that finishes the sign-up to a new address; the queued mail holds neither the token nor the address outside the ciphertext', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);

    signUpPost('Grace@Example.com', 'form')->assertSessionHasNoErrors();
    signUpRunWorker();

    $queued = signUpQueuedJob(FinishSignUp::class);
    $mail = $queued['command']->mailable;
    expect($queued['queue'])->toBe('default')
        ->and($mail)->toBeInstanceOf(FinishSignUp::class)
        ->and($mail->hasTo('grace@example.com'))->toBeTrue()
        ->and($queued['raw'])->not->toContain($mail->token)
        ->and($queued['raw'])->not->toContain('grace@example.com')
        ->and($queued['raw'])->not->toContain('Grace Hopper')
        ->and(PendingSignUps::peek($mail->token))->toBe(['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'landing' => null]);

    // The entry of the cache holds no address in clear
    $entry = Cache::get('sign-up:'.hash('sha256', $mail->token));
    expect($entry)->toBeString()->not->toContain('grace@example.com')->not->toContain('Grace Hopper');
});

test('the job sends the mail of an account to the stored address of an account, verified or not, and to no other address', function (string $case) {
    Mail::fake();
    $address = signUpCase($case);

    (new SendSignUpMail($address, 'Grace Hopper'))->handle();

    Mail::assertQueued(SignUpAccountExists::class, fn (SignUpAccountExists $mail) => $mail->hasTo('ada@example.com'));
    Mail::assertQueuedCount(1);
    Mail::assertNotQueued(FinishSignUp::class);
})->with([
    'the address of a verified account',
    'the address of an account that was never verified',
    'the address of an account in another case',
]);

test('the job sends the mail that finishes the sign-up for the pending address of another account and for the address of a deleted account', function (string $case) {
    Mail::fake();
    $address = signUpCase($case);

    (new SendSignUpMail(mb_strtolower($address), 'Grace Hopper'))->handle();

    Mail::assertQueued(FinishSignUp::class, fn (FinishSignUp $mail) => $mail->hasTo(mb_strtolower($address)));
    Mail::assertQueuedCount(1);
})->with(['the pending address of another account', 'the address of a deleted account']);

test('the mail of an account holds plain links to the sign-in page and to the reset page, no token', function () {
    $mail = (new SignUpAccountExists)->render();

    expect($mail)->toContain('Someone asked to create an account on Knowii with this email address. This address already has an account.')
        ->toContain(route('login'))->toContain(route('password.request'))
        ->toContain('Reset my password')
        ->not->toContain('signature=')->not->toContain('/login/link/')->not->toContain('/reset-password/')
        ->and((new SignUpAccountExists)->build()->subject)->toBe('You already have an account on Knowii');
});

test('the mail that finishes the sign-up has its words and its link', function () {
    $mail = new FinishSignUp(str_repeat('t', 64), 60);

    expect($mail->render())->toContain('Someone asked to create an account on Knowii with this email address. If it was you, open this link to choose your password and finish your sign-up.')
        ->toContain('Finish my sign-up')
        ->toContain('The link works once, for 60 minutes.')
        ->toContain('If you did not ask for it, ignore this email: no account is created without the link.')
        ->toContain(route('register.link.show', ['token' => str_repeat('t', 64)]))
        ->and($mail->build()->subject)->toBe('Finish your sign-up to Knowii');
});

test('a second job for the same address in the same minute sends nothing, of both kinds, whatever the case of the address', function () {
    Mail::fake();

    (new SendSignUpMail('grace@example.com', 'Grace Hopper'))->handle();
    User::factory()->create(['email' => 'grace@example.com']);
    (new SendSignUpMail('Grace@Example.com', 'Grace Hopper'))->handle();
    Mail::assertQueuedCount(1);

    $this->travel(61)->seconds();
    (new SendSignUpMail('grace@example.com', 'Grace Hopper'))->handle();
    Mail::assertQueued(SignUpAccountExists::class, 1);
    Mail::assertQueuedCount(2);

    // The key of the minute holds no address
    expect(Cache::has('sign-up-mail:'.hash_hmac('sha256', 'sign-up:grace@example.com', config()->string('app.key'))))->toBeTrue();
});

test('a job that meets an error ends as done, with a log line that holds no address, and no failed job', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = [$event->message, $event->context];
    });

    signUpPost('grace@example.com', 'form')->assertSessionHasNoErrors();
    DB::listen(function (QueryExecuted $query) {
        if (str_contains($query->sql, 'lower(email) = ?')) {
            throw new RuntimeException('The read of grace@example.com failed');
        }
    });
    signUpRunWorker();

    $lines = collect($logged)->filter(fn (array $line): bool => $line[0] === 'A sign-up job ended with an error')->values();
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and($lines)->toHaveCount(1)
        ->and(array_keys($lines[0][1]))->toBe(['job', 'error'])
        ->and($lines[0][1]['error'])->toBe(RuntimeException::class)
        ->and((string) json_encode($logged->getArrayCopy()))->not->toContain('grace@example.com');
});

// ---------------------------------------------------------------------------------------------------------------------
// The link
// ---------------------------------------------------------------------------------------------------------------------

test('end to end: the form, the worker, the link of the mail, the page, then the account, verified, with its profile and its community, and the person is signed in', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);

    signUpPost('grace@example.com', 'form')->assertSessionHasNoErrors();
    signUpRunWorker();
    signUpRunWorker();
    $token = signUpLinkToken('grace@example.com');
    expect($token)->not->toBeNull();

    // The page does not use the link up
    $this->get('/register/link/'.$token)->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/FinishSignUp')->where('valid', true)->where('email', 'grace@example.com')->where('name', 'Grace Hopper'));
    $this->get('/register/link/'.$token)->assertInertia(fn ($page) => $page->where('valid', true));
    $this->assertGuest();

    signUpFinish($token, ['name' => 'Grace B. Hopper', 'time_zone' => 'Europe/Brussels'])->assertRedirect(route('dashboard').'?verified=1');

    $grace = User::query()->where('email', 'grace@example.com')->sole();
    $this->assertAuthenticatedAs($grace);
    expect($grace->hasVerifiedEmail())->toBeTrue()
        ->and($grace->name)->toBe('Grace B. Hopper')
        ->and($grace->time_zone)->toBe('Europe/Brussels')
        ->and(Hash::check(SIGN_UP_PASSWORD, (string) $grace->password))->toBeTrue()
        ->and($grace->profile?->email)->toBe('grace@example.com')
        ->and($grace->ownedCommunities()->count())->toBe(1)
        // The session holds the version of the sessions of the account (ADR 0059)
        ->and(session()->has(EndOutdatedSessions::SESSION_KEY))->toBeTrue();
    // The verified account gets no address check
    expect(DB::table('jobs')->where('payload', 'like', '%VerifyEmail%')->count())->toBe(0);
});

test('the link works once; a field error keeps it', function () {
    Mail::fake();
    signUpPost('grace@example.com', 'form');
    $token = (string) signUpLinkToken('grace@example.com');

    signUpFinish($token, ['password_confirmation' => 'another-password'])->assertRedirect(route('register.link.show', ['token' => $token]))->assertSessionHasErrors('password');
    signUpFinish($token, ['terms' => false])->assertSessionHasErrors('terms');
    $this->assertGuest();
    expect(PendingSignUps::peek($token))->not->toBeNull();

    signUpFinish($token)->assertRedirect(route('dashboard').'?verified=1');
    auth()->logout();

    signUpFinish($token)->assertRedirect(route('register.link.show', ['token' => $token]));
    $this->assertGuest();
    $this->get('/register/link/'.$token)->assertInertia(fn ($page) => $page->where('valid', false)->where('email', null));
    expect(User::query()->where('email', 'grace@example.com')->count())->toBe(1);
});

test('the link expires after 60 minutes', function () {
    Mail::fake();
    signUpPost('grace@example.com', 'form');
    $token = (string) signUpLinkToken('grace@example.com');

    $this->travel(61)->minutes();

    $this->get('/register/link/'.$token)->assertInertia(fn ($page) => $page->where('valid', false));
    signUpFinish($token)->assertRedirect(route('register.link.show', ['token' => $token]));
    $this->assertGuest();
    expect(User::query()->where('email', 'grace@example.com')->exists())->toBeFalse();
});

test('an unknown token makes nothing', function () {
    $this->get('/register/link/'.str_repeat('x', 64))->assertOk()->assertInertia(fn ($page) => $page->where('valid', false));
    signUpFinish(str_repeat('x', 64))->assertRedirect();

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

test('the address got an account between the mail and the link: no second account, and the words of the sign-in page', function () {
    Mail::fake();
    signUpPost('grace@example.com', 'form');
    $token = (string) signUpLinkToken('grace@example.com');
    User::factory()->create(['email' => 'grace@example.com']);

    signUpFinish($token)->assertRedirect(route('login'))->assertSessionHas('status', SignUpLinkController::ADDRESS_HAS_ACCOUNT);

    $this->assertGuest();
    expect(User::query()->where('email', 'grace@example.com')->count())->toBe(1);
});

test('the address of the link is a secret address', function () {
    expect(SecretAddresses::PATHS)->toContain('register/link/*');
});

// ---------------------------------------------------------------------------------------------------------------------
// The bot checks and the limits
// ---------------------------------------------------------------------------------------------------------------------

test('a filled honeypot keeps its error and queues nothing', function () {
    Queue::fake();

    $this->post('/register', ['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'website' => 'https://spam.example'])
        ->assertSessionHasErrors(['email' => CreateNewUser::NOT_CREATED_MESSAGE]);

    Queue::assertNothingPushed();
});

test('the sign-up form takes 20 requests an hour from one IP, for any addresses: the 21st gets the same 429 for an address with and without an account', function () {
    Queue::fake();
    User::factory()->create(['email' => 'ada@example.com']);
    for ($i = 1; $i <= 20; $i++) {
        $this->postJson('/register', ['name' => 'Person', 'email' => "person{$i}@example.com"])->assertOk();
    }

    $refused = [];
    foreach (['ada@example.com', 'person21@example.com'] as $address) {
        $response = $this->postJson('/register', ['name' => 'Person', 'email' => $address])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
        $refused[] = [$response->status(), $response->json()];
    }
    expect($refused[0])->toBe($refused[1]);

    // Another IP address is not counted
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->postJson('/register', ['name' => 'Person', 'email' => 'person22@example.com'])->assertOk();
});

test('the sign-up form takes 5 requests a minute per address and IP, then the same 429', function () {
    Queue::fake();
    User::factory()->create(['email' => 'ada@example.com']);
    $answers = [];
    foreach (['ada@example.com', 'nobody@example.com'] as $address) {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/register', ['name' => 'Person', 'email' => $address])->assertOk();
        }
        $response = $this->postJson('/register', ['name' => 'Person', 'email' => $address])->assertStatus(429);
        $answers[] = [$response->status(), $response->json()];
    }

    expect($answers[0])->toBe($answers[1]);
});

test('the page of the link takes 5 requests a minute per IP address', function () {
    for ($i = 0; $i < 5; $i++) {
        signUpFinish(str_repeat('x', 64))->assertRedirect();
    }

    signUpFinish(str_repeat('x', 64))->assertStatus(429);
});

// ---------------------------------------------------------------------------------------------------------------------
// The fix round of the reviews
// ---------------------------------------------------------------------------------------------------------------------

test('the controller of the sign-up of Fortify, called directly, makes no account: the method of the contract refuses', function () {
    $request = Request::create('/register', 'POST', ['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'password' => SIGN_UP_PASSWORD, 'password_confirmation' => SIGN_UP_PASSWORD, 'terms' => true]);
    $controller = new FortifyRegisteredUserController(app(StatefulGuard::class));

    expect(fn () => $controller->store($request, app(CreatesNewUsers::class)))->toThrow(LogicException::class);

    expect(User::query()->count())->toBe(0);
    $this->assertGuest();
});

test('the owner of the mailbox chooses the name: the name of the page is the one saved, not the one of the form', function () {
    Mail::fake();
    $this->post('/register', ['name' => 'Name Of A Stranger', 'email' => 'grace@example.com']);
    $token = (string) signUpLinkToken('grace@example.com');

    // The page shows the name of the form in a field that the person can change
    $this->get('/register/link/'.$token)->assertInertia(fn ($page) => $page->where('name', 'Name Of A Stranger'));
    signUpFinish($token, ['name' => 'Grace Hopper'])->assertRedirect(route('dashboard').'?verified=1');

    expect(User::query()->where('email', 'grace@example.com')->sole()->name)->toBe('Grace Hopper');
});

test('a link opened in a session of another account goes to the dashboard with a line that says why, and stays valid', function () {
    Mail::fake();
    $this->post('/register', ['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
    $token = (string) signUpLinkToken('grace@example.com');
    $bob = User::factory()->withUserProfile()->create(['email' => 'bob@example.com']);
    $line = ['banner' => 'You are signed in with another account. Sign out, then open the link again.', 'bannerStyle' => 'danger'];

    $this->actingAs($bob)->get('/register/link/'.$token)->assertRedirect(route('dashboard'))->assertSessionHas('flash', $line);
    $this->actingAs($bob)->post('/register/link/'.$token, ['name' => 'Grace Hopper', 'password' => SIGN_UP_PASSWORD, 'password_confirmation' => SIGN_UP_PASSWORD, 'terms' => true])
        ->assertRedirect(route('dashboard'))->assertSessionHas('flash', $line);

    $this->assertAuthenticatedAs($bob);
    expect(User::query()->where('email', 'grace@example.com')->exists())->toBeFalse()
        ->and(PendingSignUps::peek($token))->not->toBeNull();
});

test('with the database cache, a sign-up that nobody finished goes at most an hour after the end of its link', function () {
    config(['cache.default' => 'database']);
    $token = PendingSignUps::start('Grace Hopper', 'grace@example.com', null);
    $rows = fn (): int => DB::table('cache')->where('key', 'like', '%sign-up:'.hash('sha256', $token))->count();
    expect($rows())->toBe(1);

    $this->travel(59)->minutes();
    $this->artisan('cache:prune-expired')->assertSuccessful();
    expect($rows())->toBe(1);

    $this->travel(2)->minutes();
    $this->artisan('cache:prune-expired')->assertSuccessful();
    expect($rows())->toBe(0);

    // The task runs every hour
    $events = collect(app(Schedule::class)->events())->filter(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'cache:prune-expired'));
    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 * * * *');
});
