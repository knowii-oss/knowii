<?php

namespace App\Enums;

// WARNING: Must remain in sync with the one defined in space.schema.ts (libs/common)
enum KnowiiSpaceType: string
{
    case Discussion = 'discussion';
    case Chat = 'chat';
    case Pages = 'pages';
    case Course = 'course';
    case Resources = 'resources';
    // A navigation link to a page outside Knowii (#1294, ADR 0026): no content, the navigation opens its URL
    case Link = 'link';

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
