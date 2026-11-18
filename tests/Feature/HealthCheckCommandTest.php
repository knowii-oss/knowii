<?php

use App\Support\MailWorkerActivity;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
 * The deep health check of a deployment (#1172): php artisan app:health. The public /up route stays cheap.
 */

test('every check passes on a healthy installation', function () {
    $this->artisan('app:health')
        ->expectsOutputToContain('OK   database')
        ->expectsOutputToContain('OK   migrations')
        ->expectsOutputToContain('OK   cache')
        ->expectsOutputToContain('OK   queue')
        ->expectsOutputToContain('OK   mail-queue')
        ->expectsOutputToContain('OK   reverb')
        ->assertSuccessful();
});

test('a migration that did not run fails the check', function () {
    DB::table('migrations')->where('id', DB::table('migrations')->max('id'))->delete();

    $this->artisan('app:health', ['--only' => 'migrations'])
        ->expectsOutputToContain('FAIL migrations: 1 migration(s) not run')
        ->assertFailed();
});

test('a database that does not answer fails the check', function () {
    $default = config()->string('database.default');
    config([
        'database.connections.unreachable' => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'nothing',
            'username' => 'nobody',
            'password' => 'nothing',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],
        'database.default' => 'unreachable',
    ]);

    try {
        $this->artisan('app:health', ['--only' => 'database'])
            ->expectsOutputToContain('FAIL database')
            ->assertFailed();
    } finally {
        config(['database.default' => $default]);
        DB::purge('unreachable');
    }
});

test('a cache store that fails fails the check', function () {
    Cache::shouldReceive('put')->once()->andThrow(new RuntimeException('the cache is down'));

    $this->artisan('app:health', ['--only' => 'cache'])
        ->expectsOutputToContain('FAIL cache: the cache is down')
        ->assertFailed();
});

test('a queue connection that fails fails the check', function () {
    Queue::shouldReceive('connection')->once()->andThrow(new RuntimeException('the queue is down'));

    $this->artisan('app:health', ['--only' => 'queue'])
        ->expectsOutputToContain('FAIL queue: the queue is down')
        ->assertFailed();
});

test('Reverb passes when it answers', function () {
    config(['broadcasting.default' => 'reverb', 'health.reverb_url' => 'http://reverb.example.com:8080/up']);
    Http::fake(['reverb.example.com:8080/*' => Http::response('', 200)]);

    $this->artisan('app:health', ['--only' => 'reverb'])
        ->expectsOutputToContain('OK   reverb')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => $request->url() === 'http://reverb.example.com:8080/up');
});

test('Reverb fails when it does not answer', function () {
    config(['broadcasting.default' => 'reverb', 'health.reverb_url' => 'http://reverb.example.com:8080/up']);
    Http::fake(function () {
        throw new ConnectionException('connection refused');
    });

    $this->artisan('app:health', ['--only' => 'reverb'])
        ->expectsOutputToContain('FAIL reverb')
        ->assertFailed();
});

test('Reverb fails when it answers with a server error', function () {
    config(['broadcasting.default' => 'reverb', 'health.reverb_url' => 'http://reverb.example.com:8080/up']);
    Http::fake(['reverb.example.com:8080/*' => Http::response('', 500)]);

    $this->artisan('app:health', ['--only' => 'reverb'])
        ->expectsOutputToContain('FAIL reverb: Reverb answered with status 500')
        ->assertFailed();
});

test('Reverb is not called when the broadcast connection is not Reverb', function () {
    config(['broadcasting.default' => 'log']);
    Http::fake();

    $this->artisan('app:health', ['--only' => 'reverb'])->assertSuccessful();

    Http::assertNothingSent();
});

/**
 * A job of the mail queue that could run since this many minutes (negative: released, it waits for a later time)
 */
function healthMailJobAvailableSince(int $minutes, string $queue = 'mail'): void
{
    DB::table('jobs')->insert([
        'queue' => $queue,
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subMinutes($minutes)->getTimestamp(),
        'created_at' => now()->subMinutes(max($minutes, 30))->getTimestamp(),
    ]);
}

test('the mail queue passes when it is empty (#1477)', function () {
    config(['queue.default' => 'database']);

    $this->artisan('app:health', ['--only' => 'mail-queue'])
        ->expectsOutputToContain('OK   mail-queue')
        ->assertSuccessful();
});

test('the mail queue fails when a job waits for more than 10 minutes: the mail worker does not run (#1477)', function () {
    config(['queue.default' => 'database', 'mail.queue' => 'mail']);
    healthMailJobAvailableSince(11);
    // Another queue is not the mail queue
    healthMailJobAvailableSince(60, 'default');

    $this->artisan('app:health', ['--only' => 'mail-queue'])
        ->expectsOutputToContain('FAIL mail-queue: 1 job(s) of the queue mail wait for more than 10 minutes: is the mail worker running?')
        ->assertFailed();
});

test('the mail queue passes with a long backlog while the mail worker ends jobs (#1477)', function () {
    config(['queue.default' => 'database', 'mail.queue' => 'mail']);
    healthMailJobAvailableSince(45);
    Carbon::setTestNow(now()->subMinutes(9));
    MailWorkerActivity::record();
    Carbon::setTestNow();

    $this->artisan('app:health', ['--only' => 'mail-queue'])
        ->expectsOutputToContain('OK   mail-queue')
        ->assertSuccessful();
});

test('the mail queue fails when the last job of the mail worker ended more than 10 minutes ago (#1477)', function () {
    config(['queue.default' => 'database', 'mail.queue' => 'mail']);
    healthMailJobAvailableSince(45);
    Carbon::setTestNow(now()->subMinutes(11));
    MailWorkerActivity::record();
    Carbon::setTestNow();

    $this->artisan('app:health', ['--only' => 'mail-queue'])
        ->expectsOutputToContain('FAIL mail-queue: 1 job(s) of the queue mail wait for more than 10 minutes: is the mail worker running?')
        ->assertFailed();
});

test('the mail queue passes with a fresh job and with a job that the rate limiter released (#1477)', function () {
    config(['queue.default' => 'database']);
    healthMailJobAvailableSince(2);
    healthMailJobAvailableSince(-1);

    $this->artisan('app:health', ['--only' => 'mail-queue'])
        ->expectsOutputToContain('OK   mail-queue')
        ->assertSuccessful();
});

test('the mail queue follows MAIL_QUEUE (#1477)', function () {
    config(['queue.default' => 'database', 'mail.queue' => 'default']);
    healthMailJobAvailableSince(11, 'default');

    $this->artisan('app:health', ['--only' => 'mail-queue'])
        ->expectsOutputToContain('FAIL mail-queue: 1 job(s) of the queue default wait for more than 10 minutes')
        ->assertFailed();
});

test('the mail queue is not checked with another driver than database (#1477)', function () {
    config(['queue.default' => 'sync']);
    healthMailJobAvailableSince(60);

    $this->artisan('app:health', ['--only' => 'mail-queue'])
        ->expectsOutputToContain('OK   mail-queue')
        ->assertSuccessful();
});

test('an unknown check is refused', function () {
    $this->artisan('app:health', ['--only' => 'database,nothing'])
        ->expectsOutputToContain('Unknown check: nothing')
        ->assertFailed();
});

test('the public /up route still answers', function () {
    $this->get('/up')->assertOk();
});
