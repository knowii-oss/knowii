<?php

namespace App\Enums;

/**
 * What a member chose for a thread (#1475, ADR 0042): `following` (told about new comments or replies), `muted` (nothing
 * reaches the member from the thread, a mention included) and `stopped` (the member stopped following by hand: the row
 * stays, so that writing in the thread again does not follow it again without a new request). No row means not following.
 */
enum KnowiiFollowState: string
{
    case Following = 'following';
    case Muted = 'muted';
    case Stopped = 'stopped';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
