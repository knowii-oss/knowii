<?php

namespace App\Enums;

/**
 * The state of a row of the delivery log (#1478, ADR 0054). `queued` when the mail goes to the queue, `sending` when the
 * job of a notification mail claimed it, `sent` when the transport accepted it, `failed` after an error of the transport
 * (the next try can claim it again), `held` while the mail budget holds notification mail, `skipped` when the address is
 * suppressed or the member stopped the group.
 */
enum KnowiiMailDeliveryStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Held = 'held';
    case Skipped = 'skipped';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
