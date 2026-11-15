<?php

namespace App\Contracts\Entitlements;

use App\Exceptions\EntitlementsReadException;
use App\Models\UserIdentity;

/**
 * Reads the entitlements of a connected account at the provider, stores the keys and runs the sync of the rules (#1386,
 * ADR 0047)
 */
interface RefreshesEntitlements
{
    /**
     * - 200: the stored keys are replaced as a whole, then the rules run.
     * - 404 (the link at the provider is gone, or the account there is deleted): the stored keys are deleted, then the rules
     *   run, which removes the rule memberships.
     * - A failure changes nothing.
     *
     * An account of another issuer than the one of this instance is never read.
     *
     * @throws EntitlementsReadException
     */
    public function refresh(UserIdentity $identity): void;
}
