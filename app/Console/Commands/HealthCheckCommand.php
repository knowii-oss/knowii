<?php

namespace App\Console\Commands;

use App\Constants;
use App\Support\MailSettings;
use App\Support\MailWorkerActivity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

/**
 * The deep health check of a deployment (#1172): the database answers, every migration ran, the cache works, the queue
 * connection works and Reverb answers. It runs on the command line only, so the details never reach the internet: the
 * public /up route stays cheap for the proxy and for Docker. The exit code is 0 when every check passes, 1 otherwise.
 * `mail-queue` (#1477, ADR 0054) finds notification mail that waits because no worker reads the mail queue.
 */
#[Signature('app:health {--only= : Run only these checks, separated by commas (database, migrations, cache, queue, mail-queue, reverb)}')]
#[Description('Check that the database, the migrations, the cache, the queue, the mail worker and Reverb work (exit code 1 on failure)')]
class HealthCheckCommand extends Command
{
    /** @var list<string> */
    private const array CHECKS = ['database', 'migrations', 'cache', 'queue', 'mail-queue', 'reverb'];

    public function handle(): int
    {
        $only = $this->option('only');
        $names = is_string($only) && trim($only) !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $only)), static fn (string $name): bool => $name !== ''))
            : self::CHECKS;

        $unknown = array_diff($names, self::CHECKS);
        if ($unknown !== []) {
            $this->error('Unknown check: '.implode(', ', $unknown).'. Known checks: '.implode(', ', self::CHECKS).'.');

            return self::FAILURE;
        }

        $failed = false;
        foreach (self::CHECKS as $name) {
            if (! in_array($name, $names, true)) {
                continue;
            }

            try {
                $problem = match ($name) {
                    'database' => $this->checkDatabase(),
                    'migrations' => $this->checkMigrations(),
                    'cache' => $this->checkCache(),
                    'queue' => $this->checkQueue(),
                    'mail-queue' => $this->checkMailQueue(),
                    default => $this->checkReverb(),
                };
            } catch (Throwable $exception) {
                $problem = $exception->getMessage();
            }

            if ($problem === null) {
                $this->line("OK   {$name}");

                continue;
            }

            $failed = true;
            $this->line("FAIL {$name}: {$problem}");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Each check returns the problem, or null when it passes. This one passes or throws
     */
    private function checkDatabase(): null
    {
        DB::select('select 1');

        return null;
    }

    private function checkMigrations(): ?string
    {
        $migrator = app('migrator');

        if (! $migrator->repositoryExists()) {
            return 'the migrations table does not exist: run php artisan migrate';
        }

        $files = $migrator->getMigrationFiles([...$migrator->paths(), database_path('migrations')]);
        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());
        if ($pending === []) {
            return null;
        }

        return count($pending).' migration(s) not run: '.implode(', ', array_slice($pending, 0, 5));
    }

    private function checkCache(): ?string
    {
        $key = 'health:'.Str::random(16);
        Cache::put($key, 'ok', 10);
        $value = Cache::get($key);
        Cache::forget($key);

        return $value === 'ok' ? null : 'the cache store did not return the value that was just written';
    }

    private function checkQueue(): null
    {
        Queue::connection()->size();

        return null;
    }

    /**
     * With the database driver: a job of the mail queue that is not reserved and could run for longer than
     * Constants::MAIL_QUEUE_STALE_MINUTES, while no worker ended a job of that queue in the same time, means that no worker
     * reads the queue. A job that the rate limiter released waits for a time in the future, so it does not count. A long
     * backlog behind the rate limit is not a failure: the worker ends jobs all the time (`MailWorkerActivity`). Another
     * driver passes: it is not checked
     */
    private function checkMailQueue(): ?string
    {
        $connection = config()->string('queue.default');
        if (config('queue.connections.'.$connection.'.driver') !== 'database') {
            return null;
        }

        $table = config('queue.connections.'.$connection.'.table');
        $database = config('queue.connections.'.$connection.'.connection');
        $queue = MailSettings::queue();

        $waiting = DB::connection(is_string($database) && $database !== '' ? $database : null)
            ->table(is_string($table) && $table !== '' ? $table : 'jobs')
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->where('available_at', '<', now()->subMinutes(Constants::MAIL_QUEUE_STALE_MINUTES)->getTimestamp())
            ->count();

        if ($waiting === 0) {
            return null;
        }

        $lastJobEndedAt = MailWorkerActivity::lastAt();
        if ($lastJobEndedAt !== null && $lastJobEndedAt->greaterThan(now()->subMinutes(Constants::MAIL_QUEUE_STALE_MINUTES))) {
            return null;
        }

        return $waiting.' job(s) of the queue '.$queue.' wait for more than '.Constants::MAIL_QUEUE_STALE_MINUTES.' minutes: is the mail worker running?';
    }

    private function checkReverb(): ?string
    {
        if (config()->string('broadcasting.default') !== 'reverb') {
            return null;
        }

        $url = config()->string('health.reverb_url');
        if ($url === '') {
            // The port comes from the environment, so it can be a numeric string
            $port = config('reverb.servers.reverb.port');
            $url = 'http://127.0.0.1:'.(is_numeric($port) ? (int) $port : 8080).'/up';
        }

        $response = Http::timeout(3)->get($url);

        return $response->serverError() ? "Reverb answered with status {$response->status()}" : null;
    }
}
