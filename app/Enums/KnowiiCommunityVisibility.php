<?php

namespace App\Enums;

// WARNING: Must remain in sync with the one defined in community.schema.ts
enum KnowiiCommunityVisibility: string
{
    case Personal = 'personal';
    case Private = 'private';
    case Public = 'public';

    public static function toCommaSeparatedString(): string
    {
        return implode(',', array_column(self::cases(), 'value'));
    }

    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
