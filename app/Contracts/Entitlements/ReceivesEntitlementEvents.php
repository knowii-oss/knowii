<?php

namespace App\Contracts\Entitlements;

use App\Exceptions\OpenIdConnectException;

/**
 * Checks a change call of the sign-in provider (#1386, ADR 0047). The call says only "something changed for this
 * subject": it is never trusted for more than the subject.
 */
interface ReceivesEntitlementEvents
{
    /**
     * Returns the subject to read again, or null when the call repeats one that was already handled (the provider retries
     * with the same `jti`: a repeat is not an error).
     *
     * @throws OpenIdConnectException When the call is not valid: the signature, the type, the issuer, the audience, the
     *                                dates, the event or the claims
     */
    public function receive(string $jws): ?string;
}
