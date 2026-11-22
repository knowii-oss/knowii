<?php

namespace App\Enums;

/**
 * The state of an invite link (#1391, ADR 0062), as InviteLink::status() reads it. Only `active` lets a person join.
 * WARNING: aligned with INVITE_LINK_STATUSES (invite-link.schema.ts)
 */
enum KnowiiInviteLinkStatus: string
{
    // On, before its end, and below its maximum number of joins
    case Active = 'active';
    // A manager turned it off
    case Off = 'off';
    // Its end is past
    case Ended = 'ended';
    // As many people joined through it as its maximum
    case Full = 'full';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
