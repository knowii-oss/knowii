<?php

namespace App\Jobs;

use App\Contracts\Entitlements\SyncsEntitlementRules;
use App\Models\Community;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the sync of the entitlement rules for every member of a community (#1386, ADR 0047): started when a rule is added,
 * changed or removed. It uses the keys that are stored and never calls the provider. A community that was deleted in the
 * meantime gives nothing to do.
 */
class SyncCommunityEntitlementRules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Community $community)
    {
        $this->afterCommit();
    }

    final public function handle(SyncsEntitlementRules $syncs): void
    {
        $syncs->syncCommunity($this->community);
    }
}
