<?php

namespace App\Enums;

/**
 * The rights that the owner and the admins of a community switch for its moderators (#1240, ADR 0017).
 *
 * WARNING: aligned with MODERATOR_PERMISSIONS (community.schema.ts)
 */
enum KnowiiModeratorPermission: string
{
    case ManageSpaces = 'manage_spaces';
    case ManageMembers = 'manage_members';
    case PinPosts = 'pin_posts';
    case DeleteContent = 'delete_content';

    /**
     * The rights of moderators in a new community: today's behavior (they moderate content, nothing more).
     *
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return [
            self::ManageSpaces->value => false,
            self::ManageMembers->value => false,
            self::PinPosts->value => true,
            self::DeleteContent->value => true,
        ];
    }
}
