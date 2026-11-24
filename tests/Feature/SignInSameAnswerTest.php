<?php

use App\Actions\Auth\MagicLinks;
use App\ApiRoutes;
use App\Jobs\SendSignInLink;
use App\Mail\MagicLink;
use App\Models\User;
use App\Support\DummyPassword;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Hashing\HashManager;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

/*
 * The sign-in link form queues one job for every address, as the password reset form, and a sign-in takes the same time
 * for an address with and without an account (documentation/architecture/backend.md).
 */

const SIGN_IN_ANSWER = "If an account has this address, we sent it a sign-in link. The link works once, for 15 minutes.\nNo email after a few minutes? Look in your spam folder, then ask again. An address that refused our mail before can need a second try.";

/**
 * The cases of an address on the sign-in forms: the address that the form gets, after the accounts of the case are made
 */
function signInCase(string $case): string
{
    return match ($case) {
        'an account' => tap('ada@example.com', fn () => User::factory()->withUserProfile()->create(['email' => 'ada@example.com'])),
        'no account' => 'nobody@example.com',
        'an account that was never verified' => tap('ada@example.com', fn () => User::factory()->unverified()->withUserProfile()->create(['email' => 'ada@example.com'])),
        'an account in another case' => tap('ADA@Example.COM', fn () => User::factory()->withUserProfile()->create(['email' => 'ada@example.com'])),
        'the pending address of another account' => tap('grace@example.com', fn () => User::factory()->withUserProfile()->create(['email' => 'ada@example.com', 'pending_email' => 'grace@example.com', 'pending_email_requested_at' => now()])),
        'an account without a password' => tap('ada@example.com', fn () => User::factory()->withUserProfile()->create(['email' => 'ada@example.com', 'password' => null])),
        'a wrong password' => tap('ada@example.com', fn () => User::factory()->withUserProfile()->create(['email' => 'ada@example.com'])),
    };
}

// ---------------------------------------------------------------------------------------------------------------------
// The sign-in link form
// ---------------------------------------------------------------------------------------------------------------------

test('the sign-in link form gives the same answer for every address, sends no mail in the request and queues one encrypted job', function (string $kind) {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    $answers = [];
    $statements = [];
    foreach (['an account', 'no account', 'an account that was never verified', 'an account in another case', 'the pending address of another account'] as $case) {
        DB::table('jobs')->delete();
        User::query()->get()->each->delete();
        $this->flushSession();
        $address = signInCase($case);
        $sql = new ArrayObject;
        DB::listen(function (QueryExecuted $query) use ($sql) {
            $sql[] = $query->sql;
        });

        $response = $this->post('/login/link', ['email' => $address], $kind === 'inertia' ? ['X-Inertia' => 'true'] : [])
            ->assertRedirect(route('login'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', SIGN_IN_ANSWER);
        Event::forget(QueryExecuted::class);

        $row = DB::table('jobs')->sole();
        $raw = (string) json_encode($row).(string) base64_decode((string) $row->payload, true);
        expect($raw)->toContain('SendSignInLink')
            ->and($raw)->not->toContain(mb_strtolower($address))
            ->and($row->queue)->toBe('default')
            ->and(Mail::mailer('array')->getSymfonyTransport()->messages())->toHaveCount(0);

        $answers[$case] = [$response->getStatusCode(), $response->headers->get('Location'), session('status')];
        $statements[$case] = $sql->getArrayCopy();
    }

    expect(collect($answers)->map(fn (array $answer) => json_encode($answer))->unique())->toHaveCount(1)
        ->and(collect($statements)->map(fn (array $list) => json_encode($list))->unique())->toHaveCount(1)
        ->and($statements['no account'])->toHaveCount(1)
        ->and($statements['no account'][0])->toStartWith('insert into "jobs"');
})->with(['form', 'inertia']);

test('the job sends the link only to an account, at its stored address, once a minute per account', function () {
    Mail::fake();
    User::factory()->withUserProfile()->create(['email' => 'ada@example.com']);

    (new SendSignInLink('nobody@example.com'))->handle(app(MagicLinks::class));
    Mail::assertNothingQueued();

    (new SendSignInLink('ADA@example.com'))->handle(app(MagicLinks::class));
    (new SendSignInLink('ada@example.com'))->handle(app(MagicLinks::class));
    Mail::assertQueued(MagicLink::class, 1);
    Mail::assertQueued(MagicLink::class, fn (MagicLink $mail) => $mail->hasTo('ada@example.com'));

    $this->travel(61)->seconds();
    (new SendSignInLink('ada@example.com'))->handle(app(MagicLinks::class));
    Mail::assertQueued(MagicLink::class, 2);
});

test('the landing of the session travels in the job to the entry of the link', function () {
    Mail::fake();
    $user = User::factory()->withUserProfile()->create(['email' => 'ada@example.com']);

    app(MagicLinks::class)->send(['email' => 'ada@example.com'], '/community/a-harbour');

    $mail = Mail::queued(MagicLink::class)->sole();
    expect(app(MagicLinks::class)->pullLanding($mail->token))->toBe('/community/a-harbour')
        ->and(app(MagicLinks::class)->peek($mail->token)?->is($user))->toBeTrue();
});

test('a job of the sign-in link that meets an error ends as done, with a log line that holds no address', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = [$event->message, $event->context];
    });

    $this->post('/login/link', ['email' => 'ada@example.com'])->assertSessionHasNoErrors();
    DB::listen(function (QueryExecuted $query) {
        if (str_contains($query->sql, 'lower(email) = ?')) {
            throw new RuntimeException('The read of ada@example.com failed');
        }
    });
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'default', '--sleep' => 0, '--tries' => 1]);

    $lines = collect($logged)->filter(fn (array $line): bool => $line[0] === 'A sign-in link job ended with an error')->values();
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and($lines)->toHaveCount(1)
        ->and($lines[0][1]['error'])->toBe(RuntimeException::class)
        ->and((string) json_encode($logged->getArrayCopy()))->not->toContain('ada@example.com');
});

test('the sign-in link mail keeps no recipient address when it fails', function () {
    expect((new MagicLink(str_repeat('t', 64)))->middleware())->toHaveCount(1);
});

test('the sign-in link form takes 20 requests an hour from one IP, for any addresses: the 21st gets the same 429', function () {
    Queue::fake();
    User::factory()->create(['email' => 'ada@example.com']);
    for ($i = 1; $i <= 20; $i++) {
        $this->postJson('/login/link', ['email' => "person{$i}@example.com"])->assertRedirect(route('login'));
    }

    $refused = [];
    foreach (['ada@example.com', 'person21@example.com'] as $address) {
        $response = $this->postJson('/login/link', ['email' => $address])->assertStatus(429);
        $refused[] = [$response->status(), $response->json()];
    }
    expect($refused[0])->toBe($refused[1]);

    // Another IP address is not counted, and the use of a link keeps its own limiter
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->postJson('/login/link', ['email' => 'person22@example.com'])->assertRedirect(route('login'));
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->post('/login/link/'.str_repeat('x', 64))->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------------------------------------------------
// The password sign-ins: one hash check for every failure
// ---------------------------------------------------------------------------------------------------------------------

/**
 * A hasher that counts its checks and the cost of each hash it compares: it wraps the hasher of the application
 */
function signInCountingHasher(): object
{
    $counter = new class(app()) extends HashManager
    {
        /** @var list<int|null> */
        public array $costs = [];

        public function check($value, $hashedValue, array $options = []): bool
        {
            $info = password_get_info((string) $hashedValue);
            $this->costs[] = is_int($info['options']['cost'] ?? null) ? $info['options']['cost'] : null;

            return parent::check($value, $hashedValue, $options);
        }
    };
    app()->instance('hash', $counter);
    Hash::clearResolvedInstance('hash');
    app('auth')->forgetGuards();

    return $counter;
}

function signInAttempt(string $how, string $address): TestResponse
{
    return $how === 'web'
        ? test()->post('/login', ['email' => $address, 'password' => 'not-the-password'])
        : test()->postJson(ApiRoutes::path(ApiRoutes::AUTH_LOGIN), ['email' => $address, 'password' => 'not-the-password']);
}

test('every failed password sign-in checks one hash, at the cost of the configuration', function (string $how, string $case) {
    $address = signInCase($case);
    $counter = signInCountingHasher();

    $response = signInAttempt($how, $address);

    $how === 'web'
        ? $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.'])
        : $response->assertStatus(401)->assertJsonPath('message', 'The provided credentials do not match our records.');
    $this->assertGuest();
    expect($counter->costs)->toBe([(int) config('hashing.bcrypt.rounds')]);
})->with(['web', 'api'])->with(['no account', 'an account without a password', 'a wrong password', 'the pending address of another account']);

test('the dummy hash: with the cost of production it is the constant, with no query and no cache', function () {
    config(['hashing.bcrypt.rounds' => 12]);
    app()->forgetInstance('hash');
    Hash::clearResolvedInstance('hash');
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    expect(DummyPassword::hash())->toBe(DummyPassword::HASH)
        ->and(password_get_info(DummyPassword::HASH)['options']['cost'])->toBe(12)
        ->and(Hash::check('anything', DummyPassword::HASH))->toBeFalse()
        ->and($queries)->toBe(0);
});

test('the dummy hash: every cost of bcrypt from 4 to 15 and argon with the options of the framework read a constant, with no query and no hash made', function (string $driver, ?int $rounds) {
    config(['hashing.driver' => $driver, ...($rounds === null ? [] : ['hashing.bcrypt.rounds' => $rounds])]);
    $counter = new class(app()) extends HashManager
    {
        public int $made = 0;

        public function make($value, array $options = []): string
        {
            $this->made++;

            return parent::make($value, $options);
        }
    };
    app()->instance('hash', $counter);
    Hash::clearResolvedInstance('hash');
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $hash = DummyPassword::hash();

    expect(DummyPassword::HASHES)->toContain($hash)
        ->and(Hash::needsRehash($hash))->toBeFalse()
        ->and($counter->made)->toBe(0)
        ->and($queries)->toBe(0);
})->with([
    'bcrypt, cost 4' => ['bcrypt', 4],
    'bcrypt, cost 10' => ['bcrypt', 10],
    'bcrypt, cost 13' => ['bcrypt', 13],
    'argon2i' => ['argon', null],
    'argon2id' => ['argon2id', null],
]);

test('the dummy hash: argon with other options is made once with the options of the application, kept in the cache, and read after', function () {
    config(['hashing.driver' => 'argon2id', 'hashing.argon.time' => 2, 'hashing.argon.memory' => 8192]);
    $counter = new class(app()) extends HashManager
    {
        public int $made = 0;

        public function make($value, array $options = []): string
        {
            $this->made++;

            return parent::make($value, $options);
        }
    };
    app()->instance('hash', $counter);
    Hash::clearResolvedInstance('hash');

    $first = DummyPassword::hash();
    $second = DummyPassword::hash();

    expect(DummyPassword::HASHES)->not->toContain($first)
        ->and(password_get_info($first)['options']['time_cost'])->toBe(2)
        ->and($second)->toBe($first)
        ->and($counter->made)->toBe(1);
});

test('with the database cache and a cost of 10, a sign-in without an account runs the same statements as a wrong password', function () {
    config(['cache.default' => 'database', 'hashing.bcrypt.rounds' => 10]);
    app()->forgetInstance('hash');
    Hash::clearResolvedInstance('hash');
    app('auth')->forgetGuards();
    $hash = Hash::make('the-password');
    $statements = [];
    foreach (['a wrong password' => 'ada@example.com', 'no account' => 'nobody@example.com'] as $case => $address) {
        User::query()->get()->each->delete();
        User::factory()->withUserProfile()->create(['email' => 'ada@example.com', 'password' => $hash]);
        app('auth')->forgetGuards();
        $sql = new ArrayObject;
        DB::listen(function (QueryExecuted $query) use ($sql) {
            $sql[] = $query->sql;
        });

        $this->withServerVariables(['REMOTE_ADDR' => $case === 'no account' ? '203.0.113.20' : '203.0.113.21'])
            ->post('/login', ['email' => $address, 'password' => 'not-the-password'])->assertSessionHasErrors('email');
        Event::forget(QueryExecuted::class);

        $statements[$case] = $sql->getArrayCopy();
    }

    expect($statements['no account'])->toBe($statements['a wrong password']);
});
