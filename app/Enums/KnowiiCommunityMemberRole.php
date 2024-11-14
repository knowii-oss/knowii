<?php

namespace App\Enums;

enum KnowiiCommunityMemberRole: string
{
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Member = 'member';

    public static function toCommaSeparatedString(): string
    {
        return implode(',', array_column(self::cases(), 'value'));
    }

    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
