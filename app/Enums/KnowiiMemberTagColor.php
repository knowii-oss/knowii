<?php

namespace App\Enums;

/**
 * The palette of the member tags (#1389, ADR 0060): one key of eight, never a free color, so that each one keeps its
 * contrast on every surface. Each key is a token `--color-tag-<key>` of each theme (styles.css).
 * WARNING: aligned with MEMBER_TAG_COLORS (member-tag.schema.ts) and the check of the migration
 */
enum KnowiiMemberTagColor: string
{
    // The default
    case Gray = 'gray';
    case Red = 'red';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case Green = 'green';
    case Teal = 'teal';
    case Blue = 'blue';
    case Purple = 'purple';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
