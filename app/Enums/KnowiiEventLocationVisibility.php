<?php

namespace App\Enums;

/**
 * Who gets the meeting address and the place of an event (#1513, ADR 0053). A visitor and a person who is not a member
 * never get them. WARNING: aligned with eventLocationVisibilitySchema (event.schema.ts)
 */
enum KnowiiEventLocationVisibility: string
{
    // Every member who can see the event
    case Members = 'members';
    // The host, the owner and the admins (later also the members who answered Yes)
    case Going = 'going';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
