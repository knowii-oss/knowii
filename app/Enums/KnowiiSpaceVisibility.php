<?php

namespace App\Enums;

// WARNING: Must remain in sync with the one defined in space.schema.ts (libs/common)
enum KnowiiSpaceVisibility: string
{
    case Public = 'public';
    case Members = 'members';
    // Only the members of the access groups of the space, plus the owner and the admins (#1241, ADR 0025)
    case Groups = 'groups';

    public static function toCommaSeparatedString(): string
    {
        return implode(',', array_column(self::cases(), 'value'));
    }

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
