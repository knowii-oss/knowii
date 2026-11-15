<?php

namespace App\Console\Commands;

use App\Contracts\Entitlements\SyncsEntitlementRules;
use App\Jobs\ReadEntitlements;
use App\Models\UserEntitlement;
use App\Models\UserIdentity;
use App\Support\OidcEntitlements;
use App\Support\OpenIdConnect;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * The nightly safety net of the entitlement rules (#1386, ADR 0047), the change calls of the provider being best effort.
 *
 * - Rule memberships go where no rule can apply any more: a community that is not listed in OIDC_ENTITLEMENT_COMMUNITIES
 *   (any more), or the feature turned off.
 * - With the feature on: one queued read for every connected account of the provider, spread over time (a read a second
 *   at most, well under the limit of the provider) so that the night does not become a burst.
 * - With the feature off: the stored keys are deleted too. Without the feature they have no use.
 */
#[Signature('entitlements:sync')]
#[Description('Read the entitlements of every connected account again and clear the rule memberships that no rule can give')]
class SyncEntitlementsCommand extends Command
{
    /**
     * Reads started per minute: the provider allows 120 per client per minute, the other reads (change calls, sign-ins) share it
     */
    public const int READS_PER_MINUTE = 60;

    private const int CHUNK = 500;

    public function handle(SyncsEntitlementRules $syncs): int
    {
        $cleared = $syncs->clearUnlisted();
        $this->line("Rule memberships cleared: {$cleared}");

        if (! OidcEntitlements::enabled()) {
            $deleted = UserEntitlement::query()->count();
            UserEntitlement::query()->delete();
            UserIdentity::query()->whereNotNull('entitlements_checked_at')->update(['entitlements_checked_at' => null]);
            $this->line("The feature is off. Stored keys deleted: {$deleted}");

            return self::SUCCESS;
        }

        $queued = 0;
        UserIdentity::query()->where('issuer', OpenIdConnect::issuer() ?? '')->chunkById(self::CHUNK, function (Collection $identities) use (&$queued): void {
            /** @var Collection<int, UserIdentity> $identities */
            foreach ($identities as $identity) {
                ReadEntitlements::dispatch($identity->id)->delay(now()->addSeconds(intdiv($queued * 60, self::READS_PER_MINUTE)));
                $queued++;
            }
        });
        $this->line("Reads queued: {$queued}");

        return self::SUCCESS;
    }
}
