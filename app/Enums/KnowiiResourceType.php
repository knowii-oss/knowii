<?php

namespace App\Enums;

// WARNING: Must remain in sync with the one defined in resource.schema.ts
enum KnowiiResourceType: string
{
    case TextArticle = 'textArticle';

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
