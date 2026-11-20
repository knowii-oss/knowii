<?php

namespace App\Enums;

/**
 * The order of the member list of a community (#148, ADR 0056). The API list takes it in `sort` and keeps `role`
 * without it; the member directory offers `name` and `newest`, and never shows `role`.
 *
 * WARNING: aligned with MEMBER_SORTS (member.schema.ts)
 */
enum KnowiiMemberSort: string
{
    /** The owner, the admins, the moderators, the members, each by username: the order of the settings and of the member fields */
    case Role = 'role';
    /** The name from A to Z, without the case, then the username */
    case Name = 'name';
    /** The date of joining, from the newest to the oldest, then the username. The owner joined at the date of the community */
    case Newest = 'newest';

    /**
     * The sort of the API list: `role` without a value, and with any value that is not a sort
     */
    public static function forApi(?string $value): self
    {
        return $value === null ? self::Role : (self::tryFrom($value) ?? self::Role);
    }

    /**
     * The sort of the member directory: `name` without a value, with any value that is not a sort, and with `role`
     */
    public static function forDirectory(?string $value): self
    {
        return $value === self::Newest->value ? self::Newest : self::Name;
    }
}
