<?php

namespace App\Enums;

/**
 * What the takeover of the first proof of an address did (ADR 0059, TakesOverUnverifiedAccounts). The caller decides on it
 * how it goes on. Only the server uses it.
 */
enum KnowiiTakeOverResult: string
{
    /** The account was taken over: the address is verified, and what signed in or acted for it before went */
    case TookOver = 'took_over';
    /**
     * The stored row has a verified address (another proof of the same address came first): the caller goes on as for a
     * verified account
     */
    case AlreadyVerified = 'already_verified';
    /**
     * The stored row has another address than the proved one, without regard to the case (a correction of the address came
     * in between): the proof is for an address that the account no longer has
     */
    case OtherAddress = 'other_address';
    /** The account was deleted after the caller read it: the proof is for an account that is gone */
    case Gone = 'gone';

    /**
     * Whether the proof is still a proof of the address of the account: the caller goes on (a takeover, or the path of a
     * verified account). Else, the caller answers as for a proof that does not work any more
     */
    public function provesTheAccount(): bool
    {
        return match ($this) {
            self::TookOver, self::AlreadyVerified => true,
            self::OtherAddress, self::Gone => false,
        };
    }
}
