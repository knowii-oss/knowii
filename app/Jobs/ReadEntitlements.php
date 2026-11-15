<?php

namespace App\Jobs;

use App\Contracts\Entitlements\RefreshesEntitlements;
use App\Exceptions\EntitlementsReadException;
use App\Models\UserIdentity;
use App\Support\OidcEntitlements;
use App\Support\OpenIdConnect;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Reads the entitlements of one connected account at the provider and runs the sync of the rules (#1386, ADR 0047). It
 * starts when the provider calls the change address, at each sign-in through the provider, when a person joins a
 * community that has rules, and for every connected account once a night (spread over time).
 *
 * The job holds only the id of the identity: never the subject, never a key.
 *
 * - A temporary failure (network, timeout, 5xx, 429) changes nothing and is tried again after a growing delay, six times at
 *   most. The nightly read is the safety net after that.
 * - A refusal of the client by the provider (401, 403) is a configuration problem: nothing changes, nothing is tried
 *   again, and one line goes to the log (without any token) once an hour, however many reads fail.
 * - An identity that was disconnected in the meantime gives nothing to do.
 */
class ReadEntitlements implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Seconds to wait before the next try, by number of tries so far
     *
     * @var list<int>
     */
    public const array BACKOFF_SECONDS = [60, 300, 1800, 7200, 21600];

    /**
     * One try and one more for each delay
     */
    public int $tries = 6;

    public function __construct(public readonly int $identityId)
    {
        $this->afterCommit();
    }

    final public function handle(RefreshesEntitlements $refreshes): void
    {
        if (! OidcEntitlements::enabled()) {
            return;
        }
        $identity = UserIdentity::query()->find($this->identityId);
        if ($identity === null) {
            return;
        }

        try {
            $refreshes->refresh($identity);
        } catch (EntitlementsReadException $e) {
            $this->handleFailure($e, $identity);
        }
    }

    private function handleFailure(EntitlementsReadException $e, UserIdentity $identity): void
    {
        if (! $e->temporary) {
            // Once an hour for the whole instance
            if (Cache::add('oidc:entitlements:config-problem', true, now()->addHour())) {
                Log::error('Entitlements: the provider refused this client, check the configuration', ['reason' => $e->reason, 'issuer' => OpenIdConnect::issuer()]);
            }

            return;
        }

        $delay = self::BACKOFF_SECONDS[$this->attempts() - 1] ?? null;
        if ($delay === null) {
            Log::warning('Entitlements: a read failed too many times, the nightly read is the next try', ['reason' => $e->reason, 'identity' => $identity->cuid]);

            return;
        }
        Log::info('Entitlements: a read failed, it will be tried again', ['reason' => $e->reason, 'identity' => $identity->cuid, 'delay' => $delay]);
        $this->release($delay);
    }
}
