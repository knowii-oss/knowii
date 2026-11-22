<?php

namespace App\Enums;

/**
 * Who sees a member tag (#1389, ADR 0060). Not "public": a tag never shows to a visitor (ADR 0044).
 * WARNING: aligned with MEMBER_TAG_VISIBILITIES (member-tag.schema.ts) and the check of the migration
 */
enum KnowiiMemberTagVisibility: string
{
    // The members of the community see it on the members who have it
    case Members = 'members';
    // Only the owner, the admins and the moderators with the "manage members" right: the default
    case Managers = 'managers';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
