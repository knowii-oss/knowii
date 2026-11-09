<?php

namespace App\Enums;

// WARNING: Must remain in sync with REACTION_KINDS (reaction.schema.ts, libs/common)
enum KnowiiReactionKind: string
{
    case Like = 'like';
    case Love = 'love';
    case Insightful = 'insightful';
    case Funny = 'funny';
    case Celebrate = 'celebrate';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
