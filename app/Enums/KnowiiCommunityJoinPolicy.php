<?php

namespace App\Enums;

/**
 * Who can join a shared community (#1486, ADR 0044)
 *
 * WARNING: Must remain in sync with the one defined in community.schema.ts
 */
enum KnowiiCommunityJoinPolicy: string
{
    /** Only people with an invitation join (the default) */
    case Invitation = 'invitation';
    /** Any signed-in person with a verified address joins by themselves, as a member */
    case Open = 'open';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
