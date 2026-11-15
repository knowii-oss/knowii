<?php

namespace App\Enums;

/**
 * Why a member has a follow row (#1475, ADR 0042): they wrote the thread, they replied in it, or they chose it by hand.
 */
enum KnowiiFollowReason: string
{
    case Wrote = 'wrote';
    case Replied = 'replied';
    case Manual = 'manual';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
