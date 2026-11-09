<?php

namespace App\Enums;

/**
 * The fixed list of resource types (ADR 0018, #1246). WARNING: Must remain in sync with resourceTypeSchema
 * (resource.schema.ts) and the icons of ResourceTypeIcon.tsx
 */
enum KnowiiResourceType: string
{
    case Article = 'article';
    case Video = 'video';
    // Podcasts
    case Audio = 'audio';
    case Book = 'book';
    case Paper = 'paper';
    // Websites and software
    case Tool = 'tool';
    case Other = 'other';

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
