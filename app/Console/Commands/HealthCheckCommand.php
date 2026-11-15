<?php

namespace App\Console\Commands;

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
 */
#[Signature('app:health {--only= : Run only these checks, separated by commas (database, migrations, cache, queue, reverb)}')]
#[Description('Check that the database, the migrations, the cache, the queue and Reverb work (exit code 1 on failure)')]
class HealthCheckCommand extends Command
{
    /** @var list<string> */
    private const array CHECKS = ['database', 'migrations', 'cache', 'queue', 'reverb'];

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
