<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The entitlements of a person could not be read (#1386, ADR 0047). Either `temporary` (the network, a timeout, a 5xx or
 * a 429 at the provider, an answer that is not what it documents: nothing changes and the read is tried again) or a
 * configuration problem (a 401 or a 403: the client is not allowed, nothing changes and an operator must look). `reason`
 * is a short code for the logs: never a token, a subject, a key or an address.
 */
final class EntitlementsReadException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly bool $temporary)
    {
        parent::__construct($reason);
    }
}
