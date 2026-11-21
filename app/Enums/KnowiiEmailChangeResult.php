<?php

namespace App\Enums;

/**
 * What the link of a change of the email address did (#1706, ADR 0058). The profile page shows the message.
 */
enum KnowiiEmailChangeResult: string
{
    /** The account has the new address now, verified */
    case Confirmed = 'confirmed';
    /** No change waits for this address: it was confirmed, cancelled or replaced, or it waited for too long */
    case NotPending = 'not_pending';
    /** Another account has the new address now: the change is cancelled */
    case AddressTaken = 'address_taken';

    public function message(): string
    {
        return match ($this) {
            self::Confirmed => 'Your new email address is confirmed.',
            self::NotPending => 'This link does not work any more. Use the link of the last mail, or change the address again.',
            self::AddressTaken => 'Another account uses this email address now. The change is cancelled.',
        };
    }
}
