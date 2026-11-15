<?php

namespace App\Contracts\Auth;

use App\Exceptions\EntitlementsReadException;
use App\Support\SubjectEntitlements;

/**
 * Reads the entitlements of one person at the sign-in provider of the instance (#1386, ADR 0047), with the credentials of
 * the client itself (the client credentials grant), never with a token of the member. Behind a contract so that tests can
 * fake the provider.
 */
interface ReadsSubjectEntitlements
{
    /**
     * The active keys of the subject, or the answer that the provider has nothing for it (404: the link at the provider is
     * gone, or the account there is deleted)
     *
     * @throws EntitlementsReadException When nothing can be said: a temporary failure (network, timeout, 5xx, 429, an answer
     *                                   that is not what the provider documents) or a configuration problem (401, 403)
     */
    public function read(string $subject): SubjectEntitlements;
}
