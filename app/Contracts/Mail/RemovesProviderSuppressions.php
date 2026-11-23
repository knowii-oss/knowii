<?php

namespace App\Contracts\Mail;

/**
 * Takes an address off the list of the mail provider (#1478, ADR 0054, groundwork 7.2): the provider skips every mail to
 * an address of its list, the sign-in link too. Best effort: it never throws.
 */
interface RemovesProviderSuppressions
{
    /**
     * True when the provider took the address off its list. False, with one log line without the address, when the
     * instance does not send through the provider, or when the removal failed
     */
    public function remove(string $address): bool;
}
