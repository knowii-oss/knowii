<?php

namespace App\Enums;

/**
 * The report of the mail provider on an invitation email (#1478, ADR 0054): `hard` for a hard bounce, a complaint or the
 * list of the provider, `soft` for a soft bounce. It is stored on the invitation and never suppresses an account.
 */
enum KnowiiInvitationBounceType: string
{
    case Hard = 'hard';
    case Soft = 'soft';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
