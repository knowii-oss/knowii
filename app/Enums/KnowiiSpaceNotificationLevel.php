<?php

namespace App\Enums;

/**
 * How close a member wants to be to a space (#1475, ADR 0042, design 2.2). The words on screen are "Everything" (`all`),
 * "Only what is for me" (`mine`) and "Muted" (`none`). A muted space gives the member nothing, a mention included.
 */
enum KnowiiSpaceNotificationLevel: string
{
    case All = 'all';
    case Mine = 'mine';
    case None = 'none';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
