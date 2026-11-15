<?php

namespace App\Contracts\Entitlements;

use App\Models\Community;
use App\Models\User;

/**
 * Puts the rule memberships of access groups in step with the entitlement keys that are stored for the members (#1386, ADR
 * 0047). It never calls the provider and never touches a membership that was added by hand.
 */
interface SyncsEntitlementRules
{
    /**
     * For one member: in each community that may use rules and where the person is a member, the wanted groups are the
     * groups with a rule whose key the member holds. `AccessGroupsChanged` goes out once per community that changed.
     */
    public function syncMember(User $user): void;

    /**
     * For every member of one community (a rule was added, changed or removed)
     */
    public function syncCommunity(Community $community): void;

    /**
     * Take every rule membership of the member away, in every community: the link to the provider is gone, or the provider
     * knows nothing about the person any more
     */
    public function clearMember(User $user): void;

    /**
     * Take the rule memberships away where no rule can apply any more: the community is not listed in
     * OIDC_ENTITLEMENT_COMMUNITIES any more, or the feature is off. Returns the number of memberships that were cleared.
     */
    public function clearUnlisted(): int;
}
